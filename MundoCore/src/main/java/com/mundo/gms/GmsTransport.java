package com.mundo.gms;

import android.os.Build;

import java.io.ByteArrayOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLDecoder;
import java.nio.charset.StandardCharsets;
import java.util.Locale;
import java.util.Map;
import java.util.zip.GZIPInputStream;
import java.util.zip.GZIPOutputStream;

public final class GmsTransport {

    private byte[] post(String url, String contentType, byte[] body, String userAgent) throws IOException {
        HttpURLConnection conn = (HttpURLConnection) new URL(url).openConnection();
        try {
            conn.setConnectTimeout(15000);
            conn.setReadTimeout(20000);
            conn.setInstanceFollowRedirects(false);
            conn.setRequestMethod("POST");
            conn.setDoOutput(true);
            conn.setRequestProperty("Content-Type", contentType);
            conn.setRequestProperty("Accept-Encoding", "gzip");
            conn.setRequestProperty("User-Agent", userAgent);

            if (url.endsWith("/checkin")) {
                conn.setRequestProperty("Content-Encoding", "gzip");
            }
            if (url.endsWith("/auth")) {
                for (String part : new String(body, StandardCharsets.UTF_8).split("&")) {
                    if (part.startsWith("app=")) {
                        conn.setRequestProperty("app", URLDecoder.decode(part.substring(4), "UTF-8"));
                    } else if (part.startsWith("androidId=")) {
                        conn.setRequestProperty("device", URLDecoder.decode(part.substring(10), "UTF-8"));
                    }
                }
            }

            OutputStream out;
            if (url.endsWith("/checkin")) {
                out = new GZIPOutputStream(conn.getOutputStream());
            } else {
                out = conn.getOutputStream();
            }
            try {
                out.write(body);
                out.close();
            } finally {
                // out closed above
            }

            int code = conn.getResponseCode();
            InputStream stream = code >= 200 && code < 300 ? conn.getInputStream() : conn.getErrorStream();
            if (stream == null) throw new IOException("Google service returned HTTP " + code);

            if ("gzip".equalsIgnoreCase(conn.getContentEncoding())) {
                stream = new GZIPInputStream(stream);
            }
            try {
                ByteArrayOutputStream baos = new ByteArrayOutputStream();
                byte[] buf = new byte[4096];
                int read;
                while ((read = stream.read(buf)) != -1) {
                    if (baos.size() + read > 4194304) throw new IOException("Google response too large");
                    baos.write(buf, 0, read);
                }
                if (url.endsWith("/auth")) return baos.toByteArray();
                if (code != 200) {
                    String msg = baos.toString("UTF-8").replaceAll("[^\\x20-\\x7e]", " ");
                    if (msg.length() > 200) msg = msg.substring(0, 200);
                    throw new IOException("Google check-in returned HTTP " + code + ": " + msg);
                }
                return baos.toByteArray();
            } finally {
                stream.close();
            }
        } finally {
            conn.disconnect();
        }
    }

    public Map<String, String> auth(Map<String, String> params) throws IOException {
        params.put("sdk_version", String.valueOf(Build.VERSION.SDK_INT));
        params.put("lang", Locale.getDefault().toString());
        params.put("device_country", Locale.getDefault().getCountry().toLowerCase(Locale.US));
        byte[] body = MicroGAuthProtocol.form(params);
        byte[] resp = post("https://android.googleapis.com/auth",
                "application/x-www-form-urlencoded", body,
                "GoogleAuth/1.4 (" + Build.DEVICE + " " + Build.ID + "); gzip");
        return MicroGAuthProtocol.response(resp);
    }

    public byte[] checkin(byte[] protoBody) throws IOException {
        return post("https://android.clients.google.com/checkin",
                "application/x-protobuffer", protoBody,
                "Android-Checkin/2.0 (" + Build.DEVICE + " " + Build.ID + "); gzip");
    }
}
