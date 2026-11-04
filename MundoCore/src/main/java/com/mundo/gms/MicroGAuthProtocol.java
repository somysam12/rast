package com.mundo.gms;

import java.io.ByteArrayOutputStream;
import java.io.IOException;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;

public final class MicroGAuthProtocol {
    public static final int GMS_VERSION = 263663000;
    public static final String GOOGLE_CERT = "38918a453d07199354f8b19af05ec6562ced5788";
    public static final String GOOGLE_PACKAGE = "com.google.android.gms";

    public static final class Field {
        public final byte[] bytes;
        public final int id;
        public final long number;

        public String text() {
            byte[] b = this.bytes;
            if (b == null) return null;
            return new String(b, StandardCharsets.UTF_8);
        }

        private Field(int id, long number, byte[] bytes) {
            this.id = id;
            this.number = number;
            this.bytes = bytes;
        }
    }

    public static final class Proto {
        private final ByteArrayOutputStream out = new ByteArrayOutputStream();

        private void varint(long v) {
            while (((-128) & v) != 0) {
                out.write((((int) v) & 127) | 128);
                v >>>= 7;
            }
            out.write((int) v);
        }

        public Proto bytes(int id, byte[] data) {
            varint((id << 3) | 2);
            varint(data.length);
            out.write(data, 0, data.length);
            return this;
        }

        public byte[] encode() {
            return out.toByteArray();
        }

        public Proto fixed64(int id, long v) {
            varint((id << 3) | 1);
            for (int i = 0; i < 8; i++) out.write(((int) (v >>> (i * 8))) & 255);
            return this;
        }

        public Proto number(int id, long v) {
            varint(id << 3);
            varint(v);
            return this;
        }

        public Proto string(int id, String s) {
            if (s != null) bytes(id, s.getBytes(StandardCharsets.UTF_8));
            return this;
        }
    }

    private MicroGAuthProtocol() {}

    public static Map<String, String> authFields(String androidId, String email, String token,
            String service, String app, String clientSig) {
        LinkedHashMap<String, String> m = new LinkedHashMap<>();
        m.put("accountType", "HOSTED_OR_GOOGLE");
        m.put("source", "android");
        m.put("app", app);
        m.put("client_sig", clientSig);
        m.put("callerPkg", "com.google.android.gms");
        m.put("callerSig", GOOGLE_CERT);
        m.put("is_called_from_account_manager", "1");
        m.put("_opt_is_called_from_account_manager", "1");
        m.put("androidId", androidId);
        m.put("Email", email);
        m.put("Token", token);
        m.put("service", service);
        m.put("google_play_services_version", String.valueOf(GMS_VERSION));
        m.put("get_accountid", "1");
        return m;
    }

    public static List<Field> decode(byte[] data) throws IOException {
        if (data.length > 4194304) throw new IOException("Google protocol message too large");
        ArrayList<Field> list = new ArrayList<>();
        int[] pos = {0};
        while (pos[0] < data.length) {
            long tag = varint(data, pos);
            int id = (int) (tag >>> 3);
            int wire = (int) (tag & 7);
            if (id <= 0) throw new IOException("Invalid protobuf field");
            byte[] bytes = null;
            long number = 0;
            if (wire == 0) {
                number = varint(data, pos);
            } else if (wire == 1 || wire == 5) {
                int len = wire == 1 ? 8 : 4;
                if (len > data.length - pos[0]) throw new IOException("Truncated protobuf number");
                for (int i = 0; i < len; i++) number |= (data[pos[0]++] & 255L) << (i * 8);
            } else if (wire == 2) {
                long blen = varint(data, pos);
                if (blen < 0 || blen > data.length - pos[0]) throw new IOException("Invalid protobuf length");
                int ilen = (int) blen;
                bytes = Arrays.copyOfRange(data, pos[0], pos[0] + ilen);
                pos[0] += ilen;
            } else {
                throw new IOException("Unsupported protobuf wire type");
            }
            list.add(new Field(id, number, bytes));
        }
        return list;
    }

    public static byte[] form(Map<String, String> params) throws IOException {
        StringBuilder sb = new StringBuilder();
        for (Map.Entry<String, String> e : params.entrySet()) {
            if (e.getValue() != null) {
                if (sb.length() != 0) sb.append('&');
                sb.append(URLEncoder.encode(e.getKey(), "UTF-8"));
                sb.append('=');
                sb.append(URLEncoder.encode(e.getValue(), "UTF-8"));
            }
        }
        return sb.toString().getBytes(StandardCharsets.UTF_8);
    }

    public static Map<String, String> response(byte[] data) throws IOException {
        LinkedHashMap<String, String> m = new LinkedHashMap<>();
        for (String line : new String(data, StandardCharsets.UTF_8).split("\n")) {
            if (!line.trim().isEmpty()) {
                int eq = line.indexOf('=');
                if (eq > 0) {
                    m.put(line.substring(0, eq).trim(), line.substring(eq + 1).trim());
                } else {
                    throw new IOException("Malformed Google authentication response");
                }
            }
        }
        return m;
    }

    private static long varint(byte[] data, int[] pos) throws IOException {
        long v = 0;
        for (int shift = 0; shift < 64; shift += 7) {
            if (pos[0] >= data.length) throw new IOException("Truncated protobuf varint");
            byte b = data[pos[0]++];
            if (shift == 63 && (b & 254) != 0) throw new IOException("Overflowing protobuf varint");
            v |= (b & 127L) << shift;
            if ((b & 128) == 0) return v;
        }
        throw new IOException("Invalid protobuf varint");
    }
}
