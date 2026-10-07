package com.mundo.core;

import android.os.Binder;
import android.os.Build;
import android.os.Process;
import androidx.annotation.Keep;
import android.content.Context;
import java.io.File;
import java.util.List;
import dalvik.system.DexFile;
import com.mundo.MundoCore;
import com.mundo.app.BActivityThread;
import com.mundo.utils.compat.DexFileCompat;

public class VNative {
    
    public static final String TAG = "VNative";
    private static boolean isInjected = false;
    public static String libtarget = "libbgmi.so";

    static {
        System.loadLibrary("EliteCore");
        File file = new File(MundoCore.getContext().getFilesDir(), "loader/" + libtarget);
        if (file.exists()) {
            if (isValidElf(file)) {
                System.load(file.getAbsolutePath());
            } else {
                // Corrupted ELF (incomplete OBB extraction). Delete so next launch re-extracts.
                file.delete();
            }
        }
    }

    private static boolean isValidElf(File file) {
        try (java.io.FileInputStream fis = new java.io.FileInputStream(file)) {
            byte[] magic = new byte[4];
            if (fis.read(magic) != 4) return false;
            return magic[0] == 0x7f && magic[1] == 'E' && magic[2] == 'L' && magic[3] == 'F';
        } catch (Exception e) {
            return false;
        }
    }

    public static native void init(int apiLevel);
    public static native void enableIO();
    public static native void addIORule(String targetPath, String relocatePath);
    public static native void hideXposed();
    
    @Keep
    public static int getCallingUid(int origCallingUid) {
        if (origCallingUid > 0 && origCallingUid < Process.FIRST_APPLICATION_UID) return origCallingUid;
        if (origCallingUid > Process.LAST_APPLICATION_UID) return origCallingUid;
        if (origCallingUid == MundoCore.getHostUid()) {
            if(BActivityThread.getAppPackageName().equals("com.google.android.gms")){
                return Process.ROOT_UID;
            }
            if(BActivityThread.getAppPackageName().equals("com.google.android.webview")){
                return Process.myUid();
            }
            return BActivityThread.getCallingBUid();
        }
        return origCallingUid;
    }

    @Keep
    public static String redirectPath(String path) {
        return VCore.get().redirectPath(path);
    }

    @Keep
    public static File redirectPath(File path) {
        return VCore.get().redirectPath(path);
    }
}
