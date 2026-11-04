package com.mundo.gms;

import android.content.Context;
import android.os.Build;
import android.security.keystore.KeyGenParameterSpec;
import android.util.AtomicFile;

import org.json.JSONObject;

import java.io.File;
import java.io.FileOutputStream;
import java.io.IOException;
import java.security.KeyStore;
import java.util.Arrays;

import javax.crypto.Cipher;
import javax.crypto.KeyGenerator;
import javax.crypto.SecretKey;
import javax.crypto.spec.GCMParameterSpec;

public final class GmsStore {

    private final String alias;
    private final Context context;
    private final AtomicFile file;

    public GmsStore(Context context) {
        this.context = context.getApplicationContext();
        this.alias = context.getPackageName() + ".mundo.gms.store.v1";
        this.file = new AtomicFile(new File(context.getNoBackupFilesDir(), "mundo-gms-v1.enc"));
    }

    private SecretKey key() throws Exception {
        if (Build.VERSION.SDK_INT < 23) {
            throw new IOException("Google login requires Android 6+ for secure credential storage");
        }
        KeyStore ks = KeyStore.getInstance("AndroidKeyStore");
        ks.load(null);
        if (!ks.containsAlias(alias)) {
            KeyGenerator kg = KeyGenerator.getInstance("AES", "AndroidKeyStore");
            kg.init(new KeyGenParameterSpec.Builder(alias,
                    KeyGenParameterSpec.PURPOSE_ENCRYPT | KeyGenParameterSpec.PURPOSE_DECRYPT)
                    .setBlockModes("GCM")
                    .setEncryptionPaddings("NoPadding")
                    .build());
            kg.generateKey();
        }
        return (SecretKey) ks.getKey(alias, null);
    }

    public synchronized JSONObject read() {
        try {
            if (!file.getBaseFile().exists()) return new JSONObject();
            byte[] raw = file.readFully();
            if (raw.length < 30) throw new IOException("Invalid credential store");
            if (raw[0] != 1) throw new IOException("Invalid credential store version");
            int ivLen = raw[1] & 255;
            if (ivLen != 12 || raw.length <= ivLen + 2) throw new IOException("Invalid credential store nonce");
            Cipher cipher = Cipher.getInstance("AES/GCM/NoPadding");
            cipher.init(Cipher.DECRYPT_MODE, key(), new GCMParameterSpec(128, Arrays.copyOfRange(raw, 2, 2 + ivLen)));
            cipher.updateAAD(context.getPackageName().getBytes("UTF-8"));
            byte[] plain = cipher.doFinal(raw, 2 + ivLen, raw.length - 2 - ivLen);
            try {
                return new JSONObject(new String(plain, "UTF-8"));
            } finally {
                Arrays.fill(plain, (byte) 0);
            }
        } catch (Exception e) {
            return new JSONObject();
        }
    }

    public synchronized void write(JSONObject data) throws Exception {
        Cipher cipher = Cipher.getInstance("AES/GCM/NoPadding");
        cipher.init(Cipher.ENCRYPT_MODE, key());
        cipher.updateAAD(context.getPackageName().getBytes("UTF-8"));
        byte[] plain = data.toString().getBytes("UTF-8");
        try {
            byte[] enc = cipher.doFinal(plain);
            FileOutputStream fos = file.startWrite();
            try {
                fos.write(1);
                fos.write(cipher.getIV().length);
                fos.write(cipher.getIV());
                fos.write(enc);
                file.finishWrite(fos);
            } catch (Exception e) {
                file.failWrite(fos);
                throw e;
            }
        } finally {
            Arrays.fill(plain, (byte) 0);
        }
    }
}
