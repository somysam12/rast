package com.mundo.fake.service;

import android.content.ComponentName;
import android.content.Intent;
import android.content.pm.ActivityInfo;
import android.content.pm.ApplicationInfo;
import android.content.pm.PackageInfo;
import android.content.pm.PackageManager;
import android.content.pm.PermissionInfo;
import android.content.pm.ProviderInfo;
import android.content.pm.ResolveInfo;
import android.content.pm.ServiceInfo;
import android.content.pm.Signature;
import android.content.pm.VersionedPackage;
import android.os.Build;
import com.mundo.fake.frameworks.BPackageManager;
import java.lang.reflect.Method;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

import black.android.app.BRActivityThread;
import black.android.app.BRContextImpl;

import com.mundo.MundoCore;
import com.mundo.app.BActivityThread;
import com.mundo.core.GmsCore;
import com.mundo.core.env.AppSystemEnv;

import com.mundo.fake.hook.BinderInvocationStub;
import com.mundo.fake.hook.MethodHook;
import com.mundo.fake.hook.ProxyMethod;
import com.mundo.fake.service.base.PkgMethodProxy;
import com.mundo.fake.service.base.ValueMethodProxy;
import com.mundo.utils.MethodParameterUtils;
import com.mundo.utils.Reflector;
import com.mundo.utils.compat.BuildCompat;
import com.mundo.utils.compat.ParceledListSliceCompat;

/**
 * Created by Milk on 3/30/21.
 * * ∧＿∧
 * (`･ω･∥
 * 丶　つ０
 * しーＪ
 * 此处无Bug
 */
public class IPackageManagerProxy extends BinderInvocationStub {
    public static final String TAG = "PackageManagerStub";

    public IPackageManagerProxy() {
        super(BRActivityThread.get().sPackageManager().asBinder());
    }

    @Override
    protected Object getWho() {
        return BRActivityThread.get().sPackageManager();
    }

    @Override
    protected void inject(Object baseInvocation, Object proxyInvocation) {
        BRActivityThread.get()._set_sPackageManager(proxyInvocation);
        replaceSystemService("package");
        Object systemContext = BRActivityThread.get(MundoCore.mainThread()).getSystemContext();
        BRContextImpl.get(systemContext).getPackageManager();
        PackageManager mPackageManager = BRContextImpl.get(systemContext).mPackageManager();
        if (mPackageManager != null) {
            try {
                Reflector.on("android.app.ApplicationPackageManager").field("mPM").set(mPackageManager, proxyInvocation);
            } catch (Exception ignored) { }
        }
    }

    @Override
    public boolean isBadEnv() {
        return false;
    }

    @Override
    protected void onBindMethod() {
        super.onBindMethod();
        addMethodHook(new ValueMethodProxy("addOnPermissionsChangeListener", 0));
        addMethodHook(new ValueMethodProxy("removeOnPermissionsChangeListener", 0));
        addMethodHook(new PkgMethodProxy("shouldShowRequestPermissionRationale"));
        if (BuildCompat.isT()) {
            return;
        }
    }
    
    @ProxyMethod("resolveIntent")
    public static class ResolveIntent extends MethodHook {
        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            Intent intent = (Intent) args[0];
            String resolvedType = (String) args[1];
            int flags = Integer.parseInt(args[2] + "");
            ResolveInfo resolveInfo = MundoCore.getBPackageManager().resolveIntent(intent, resolvedType, flags, BActivityThread.getUserId());
            if (resolveInfo != null) {
                return resolveInfo;
            }
            return method.invoke(who, args);
        }
    }

    @ProxyMethod("resolveService")
    public static class ResolveService extends MethodHook {
        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            Intent intent = (Intent) args[0];
            String resolvedType = (String) args[1];
            int flags = Integer.parseInt(args[2] + "");
            ResolveInfo resolveInfo = MundoCore.getBPackageManager().resolveService(intent, flags, resolvedType, BActivityThread.getUserId());
            if (resolveInfo != null) {
                return resolveInfo;
            }
            return method.invoke(who, args);
        }
    }

    @ProxyMethod("setComponentEnabledSetting")
    public static class SetComponentEnabledSetting extends MethodHook {
        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            return 0;
        }
    }

    @ProxyMethod("getPackageInfo")
    public static class GetPackageInfo extends MethodHook {
        private static final int GMS_SPOOFED_VERSION = 263663000;
        private static final int GMS_SIG_FLAGS = PackageManager.GET_SIGNATURES | 0x8000000;
        // Real Google Play Services signing certificate (DER-encoded X.509, hex).
        // This is the well-known public cert used to sign all GMS releases.
        // SHA-1: 38918a453d07199354f8b19af05ec6562ced5788
        private static final String GMS_CERT_HEX =
            "308204433082032ba003020102020900c2e08746644a308d300d06092a864886f70d01010405003074310b3009"
            + "060355040613025553311330110603550408130a43616c69666f726e6961311630140603550407130d4d6f756e74"
            + "61696e205669657731143012060355040a130b476f6f676c6520496e632e3110300e060355040b1307416e64726f"
            + "69643110300e06035504031307416e64726f6964301e170d3038303832313233313333345a170d3336303130373"
            + "23331333334345a3074310b3009060355040613025553311330110603550408130a43616c69666f726e6961311630"
            + "140603550407130d4d6f756e7461696e205669657731143012060355040a130b476f6f676c6520496e632e3110"
            + "300e060355040b1307416e64726f69643110300e06035504031307416e64726f696430820120300d06092a864886"
            + "f70d01010105000382010d00308201080282010100ab562e00d83ba208ae0a966f124e29da11f2ab56d08f58e2cc"
            + "a91303e9b754d372f640a71b1dcb130967624e4656a7776a92193db2e5bfb724a91e77188b0e6a47a43b33d9609"
            + "b77183145ccdf7b2e586674c9e1565b1f4c6a5955bff251a63dabf9c55c27222252e875e4f8154a645f897168c0"
            + "b1bfc612eabf785769bb34aa7984dc7e2ea2764cae8307d8c17154d7ee5f64a51a44a602c249054157dc02cd5f5"
            + "c0e55fbef8519fbe327f0b1511692c5a06f19d18385f5c4dbc2d6b93f68cc2979c70e18ab93866b3bd5db899955"
            + "2a0e3b4c99df58fb918bedc182ba35e003c1b4b10dd244a8ee24fffd333872ab5221985edab0fc0d0b145b6aa19"
            + "2858e79020103a381d93081d6301d0603551d0e04160414c77d8cc2211756259a7fd382df6be398e4d786a53081a"
            + "60603551d2304819e30819b8014c77d8cc2211756259a7fd382df6be398e4d786a5a178a4763074310b300906035"
            + "5040613025553311330110603550408130a43616c69666f726e6961311630140603550407130d4d6f756e7461696e"
            + "205669657731143012060355040a130b476f6f676c6520496e632e3110300e060355040b1307416e64726f696431"
            + "10300e06035504031307416e64726f6964820900c2e08746644a308d300c0603551d13040530030101ff300d0609"
            + "2a864886f70d010104050003820101006dd252ceef85302c360aaace939bcff2cca904bb5d7a1661f8ae46b29942"
            + "04d0ff4a68c7ed1a531ec4595a623ce60763b167297a7ae35712c407f208f0cb109429124d7b106219c084ca3eb3"
            + "f9ad5fb871ef92269a8be28bf16d44c8d9a08e6cb2f005bb3fe2cb96447e868e731076ad45b33f6009ea19c161e"
            + "62641aa99271dfd5228c5c587875ddb7f452758d661f6cc0cccb7352e424cc4365c523532f7325137593c4ae341f"
            + "4db41edda0d0b1071a7c440f0fe9ea01cb627ca674369d084bd2fd911ff06cdbf2cfa10dc0f893ae35762919048c"
            + "7efc64c7144178342f70581c9de573af55b390dd7fdb9418631895d5f759f30112687ff621410c069308a";
        private static volatile Signature sGmsCertSignature;

        private static Signature getGmsCertSignature() {
            if (sGmsCertSignature == null) {
                sGmsCertSignature = new Signature(GMS_CERT_HEX);
            }
            return sGmsCertSignature;
        }

        private static PackageInfo spoofGmsVersion(PackageInfo pi) {
            if (pi == null) {
                pi = new PackageInfo();
                pi.packageName = GmsCore.GMS_PKG;
                pi.applicationInfo = new android.content.pm.ApplicationInfo();
                pi.applicationInfo.packageName = GmsCore.GMS_PKG;
                pi.applicationInfo.enabled = true;
            }
            pi.versionCode = GMS_SPOOFED_VERSION;
            if (android.os.Build.VERSION.SDK_INT >= 28) {
                try {
                    java.lang.reflect.Field lvc = PackageInfo.class.getDeclaredField("longVersionCode");
                    lvc.setAccessible(true);
                    lvc.set(pi, (long) GMS_SPOOFED_VERSION);
                } catch (Throwable ignored) {}
            }
            // Inject real Google cert if signatures are absent.
            if (pi.signatures == null || pi.signatures.length == 0) {
                pi.signatures = new Signature[]{getGmsCertSignature()};
            }
            // Build SigningInfo for API 28+ (required by modern GMS SDK checks).
            if (android.os.Build.VERSION.SDK_INT >= 28 && pi.signingInfo == null) {
                try {
                    Signature[] sigs = pi.signatures;
                    // Try android.content.pm.SigningDetails (Android 13+) first.
                    Class<?> sdClass = null;
                    try { sdClass = Class.forName("android.content.pm.SigningDetails"); }
                    catch (ClassNotFoundException ignored) {}
                    if (sdClass == null) {
                        try { sdClass = Class.forName("android.content.pm.PackageParser$SigningDetails"); }
                        catch (ClassNotFoundException ignored) {}
                    }
                    if (sdClass != null) {
                        // SigningDetails(Signature[], int schemeVersion)
                        java.lang.reflect.Constructor<?> c = sdClass.getDeclaredConstructor(Signature[].class, int.class);
                        c.setAccessible(true);
                        Object sd = c.newInstance(sigs, 2);
                        // SigningInfo(SigningDetails)
                        java.lang.reflect.Constructor<?> c2 = android.content.pm.SigningInfo.class.getDeclaredConstructor(sdClass);
                        c2.setAccessible(true);
                        pi.signingInfo = (android.content.pm.SigningInfo) c2.newInstance(sd);
                    }
                } catch (Throwable ignored) {}
            }
            return pi;
        }

        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            String packageName = (String) args[0];
            int flags = MethodParameterUtils.toInt(args[1]);
            if (GmsCore.GMS_PKG.equals(packageName)) {
                // Try to get real GMS info from system (may succeed if GMS installed on host).
                PackageInfo pi = null;
                try {
                    Object[] sysArgs = args.clone();
                    if (sysArgs.length >= 3) sysArgs[2] = 0; // userId=0
                    if (sysArgs[1] instanceof Long) {
                        sysArgs[1] = ((Long) sysArgs[1]) | (long) GMS_SIG_FLAGS;
                    } else {
                        sysArgs[1] = ((Integer) sysArgs[1]) | GMS_SIG_FLAGS;
                    }
                    pi = (PackageInfo) method.invoke(who, sysArgs);
                } catch (Throwable ignored) {}
                // spoofGmsVersion fills in missing cert and bumps versionCode.
                return spoofGmsVersion(pi);
            }
            if (GmsCore.setGoogleAppOrService(packageName)) {
                return method.invoke(who, args);
            }
            PackageInfo packageInfo = MundoCore.getBPackageManager().getPackageInfo(packageName, flags, BActivityThread.getUserId());
            if (packageInfo != null) {
                return packageInfo;
            }
            if (AppSystemEnv.isOpenPackage(packageName)) {
                return method.invoke(who, args);
            }
            return null;
        }
    }

    @ProxyMethod("getPackageUid")
    public static class GetPackageUid extends MethodHook {
        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            MethodParameterUtils.replaceFirstAppPkg(args);
            return method.invoke(who, args);
        }
    }
    
    @ProxyMethod("setApplicationBlockedSettingAsUser")
    public static class setApplicationBlockedSettingAsUser extends MethodHook {
        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            MethodParameterUtils.replaceFirstAppPkg(args);
            MethodParameterUtils.replaceLastUserId(args);
            return method.invoke(who, args);
        }
    }
    
    @ProxyMethod("getPackageUidEtc")
    public static class getPackageUidEtc extends GetPackageUid {

    }

    @ProxyMethod("getProviderInfo")
    public static class GetProviderInfo extends MethodHook {
        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            ComponentName componentName = (ComponentName) args[0];
            int flags = MethodParameterUtils.toInt(args[1]);
            ProviderInfo providerInfo = MundoCore.getBPackageManager().getProviderInfo(componentName, flags, BActivityThread.getUserId());
            if (providerInfo != null)
                return providerInfo;
            if (AppSystemEnv.isOpenPackage(componentName)) {
                return method.invoke(who, args);
            }
            return null;
        }
    }

    @ProxyMethod("getReceiverInfo")
    public static class GetReceiverInfo extends MethodHook {
        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            ComponentName componentName = (ComponentName) args[0];
            int flags = MethodParameterUtils.toInt(args[1]);
            ActivityInfo receiverInfo = MundoCore.getBPackageManager().getReceiverInfo(componentName, flags, BActivityThread.getUserId());
            if (receiverInfo != null)
                return receiverInfo;
            if (AppSystemEnv.isOpenPackage(componentName)) {
                return method.invoke(who, args);
            }
            return null;
        }
    }

    @ProxyMethod("getActivityInfo")
    public static class GetActivityInfo extends MethodHook {
        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            ComponentName componentName = (ComponentName) args[0];
            int flags = MethodParameterUtils.toInt(args[1]);
            ActivityInfo activityInfo = MundoCore.getBPackageManager().getActivityInfo(componentName, flags, BActivityThread.getUserId());
            if (activityInfo != null)
                return activityInfo;
            if (AppSystemEnv.isOpenPackage(componentName)) {
                return method.invoke(who, args);
            }
            return null;
        }
    }

    @ProxyMethod("getServiceInfo")
    public static class GetServiceInfo extends MethodHook {

        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            ComponentName componentName = (ComponentName) args[0];
            int flags = MethodParameterUtils.toInt(args[1]);
            ServiceInfo serviceInfo = MundoCore.getBPackageManager().getServiceInfo(componentName, flags, BActivityThread.getUserId());
            if (serviceInfo != null)
                return serviceInfo;
            if (AppSystemEnv.isOpenPackage(componentName)) {
                return method.invoke(who, args);
            }
            return null;
        }
    }

    @ProxyMethod("getInstalledApplications")
    public static class GetInstalledApplications extends MethodHook {

        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            int flags = MethodParameterUtils.toInt(args[0]);
            List<ApplicationInfo> installedApplications = MundoCore.getBPackageManager().getInstalledApplications(flags, BActivityThread.getUserId());
            return ParceledListSliceCompat.create(installedApplications);
        }
    }

    @ProxyMethod("getInstalledPackages")
    public static class GetInstalledPackages extends MethodHook {

        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            int flags = MethodParameterUtils.toInt(args[0]);
            List<PackageInfo> installedPackages = MundoCore.getBPackageManager().getInstalledPackages(flags, BActivityThread.getUserId());
            return ParceledListSliceCompat.create(installedPackages);
        }
    }

    @ProxyMethod("getApplicationInfo")
    public static class GetApplicationInfo extends MethodHook {
        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            String packageName = (String) args[0];
            int flags = MethodParameterUtils.toInt(args[1]);
            // Spoof GMS: return a valid ApplicationInfo so the GMS SDK's enabled-check passes.
            // Use userId=0 so the system PM finds GMS regardless of the virtual userId.
            if (GmsCore.GMS_PKG.equals(packageName)) {
                ApplicationInfo ai = null;
                try {
                    Object[] sysArgs = args.clone();
                    if (sysArgs.length >= 3) sysArgs[2] = 0;
                    ai = (ApplicationInfo) method.invoke(who, sysArgs);
                } catch (Throwable ignored) {}
                if (ai == null) {
                    try { ai = (ApplicationInfo) method.invoke(who, args); } catch (Throwable ignored) {}
                }
                if (ai == null) {
                    ai = new ApplicationInfo();
                    ai.packageName = GmsCore.GMS_PKG;
                    ai.flags = android.content.pm.ApplicationInfo.FLAG_INSTALLED;
                }
                ai.enabled = true;
                return ai;
            }
            ApplicationInfo applicationInfo = MundoCore.getBPackageManager().getApplicationInfo(packageName, flags, BActivityThread.getUserId());
            if (GmsCore.isGoogleAppOrService(packageName)) {
				return method.invoke(who, args);
			}
            if (applicationInfo != null) {
                return applicationInfo;
            }
            if (AppSystemEnv.isOpenPackage(packageName)) {
                ApplicationInfo info = (ApplicationInfo) method.invoke(who, args);
                if (info != null && Build.VERSION.SDK_INT >= Build.VERSION_CODES.S
                        && info.targetSdkVersion >= Build.VERSION_CODES.S) {
                    info.targetSdkVersion = Build.VERSION_CODES.R;
                }
                return info;
            }
            return null;
        }
    }

    @ProxyMethod("queryContentProviders")
    public static class QueryContentProviders extends MethodHook {
        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            int flags = MethodParameterUtils.toInt(args[2]);
            List<ProviderInfo> providers = MundoCore.getBPackageManager().queryContentProviders(BActivityThread.getAppProcessName(), BActivityThread.getBUid(), flags, BActivityThread.getUserId());
            return ParceledListSliceCompat.create(providers);
        }
    }

    @ProxyMethod("queryIntentActivities")
    public static class QueryIntentActivities extends MethodHook {
        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            Intent intent = MethodParameterUtils.getFirstParam(args, Intent.class);
            if (intent == null) return method.invoke(who, args);
            String scheme = intent.getData() != null ? intent.getData().getScheme() : null;
            // Intercept fbconnect:// (and fb<APP_ID>://) scheme queries from browsers.
            // When Chrome/Samsung Internet resolves the fbconnect:// CCT redirect, we
            // substitute the real system results with the virtual guest's activity so
            // the redirect lands inside the virtual space instead of being unhandled.
            boolean isFbScheme = scheme != null && scheme.startsWith("fb")
                    && !scheme.equals("http") && !scheme.equals("https");
            if (isFbScheme) {
                String guestPkg = com.mundo.proxy.FacebookLoginHelper.getLoginTargetPackage();
                if (guestPkg != null) {
                    List<ResolveInfo> guestResults = MundoCore.getBPackageManager()
                            .queryIntentActivities(intent, 0, null, BActivityThread.getUserId());
                    if (guestResults != null && !guestResults.isEmpty()) {
                        return ParceledListSliceCompat.create(guestResults);
                    }
                }
            }
            return method.invoke(who, args);
        }
    }

    @ProxyMethod("queryIntentReceivers")
    public static class QueryBroadcastReceivers extends MethodHook {
        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            Intent intent = MethodParameterUtils.getFirstParam(args, Intent.class);
            String type = MethodParameterUtils.getFirstParam(args, String.class);
            Integer flags = MethodParameterUtils.getFirstParam(args, Integer.class);
            int flagValue = flags != null ? flags.intValue() : 0;
            List<ResolveInfo> resolves = MundoCore.getBPackageManager().queryBroadcastReceivers(intent, flagValue, type, BActivityThread.getUserId());

            // http://androidxref.com/7.0.0_r1/xref/frameworks/base/core/java/android/app/ApplicationPackageManager.java#872
            if (BuildCompat.isN()) {
                return ParceledListSliceCompat.create(resolves);
            }

            // http://androidxref.com/6.0.1_r10/xref/frameworks/base/core/java/android/app/ApplicationPackageManager.java#699
            return resolves;
        }
    }

    @ProxyMethod("resolveContentProvider")
    public static class ResolveContentProvider extends MethodHook {
        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            String authority = (String) args[0];
            int flags = MethodParameterUtils.toInt(args[1]);
            ProviderInfo providerInfo = MundoCore.getBPackageManager().resolveContentProvider(authority, flags, BActivityThread.getUserId());
            if (providerInfo == null) {
                return method.invoke(who, args);
            }
            return providerInfo;
        }
    }

    @ProxyMethod("canRequestPackageInstalls")
    public static class CanRequestPackageInstalls extends MethodHook {
        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            MethodParameterUtils.replaceFirstAppPkg(args);
            return method.invoke(who, args);
        }
    }

    @ProxyMethod("getPackagesForUid")
    public static class GetPackagesForUid extends MethodHook {
        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            int uid = MethodParameterUtils.toInt(args[0]);
            if (uid == MundoCore.getHostUid()) {
                args[0] = BActivityThread.getBUid();
                uid = MethodParameterUtils.toInt(args[0]);
            }
            String[] packagesForUid = MundoCore.getBPackageManager().getPackagesForUid(uid);
            return packagesForUid;
        }
    }

    @ProxyMethod("getInstallerPackageName")
    public static class GetInstallerPackageName extends MethodHook {
        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            return GmsCore.VENDING_PKG;
        }
    }

    @ProxyMethod("getSharedLibraries")
    public static class GetSharedLibraries extends MethodHook {
        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            // todo
            return ParceledListSliceCompat.create(new ArrayList<>());
        }
    }

    @ProxyMethod("getComponentEnabledSetting")
    public static class getComponentEnabledSetting extends MethodHook {
        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            ComponentName componentName = (ComponentName) args[0];
            String packageName = componentName.getPackageName();
            ApplicationInfo applicationInfo = MundoCore.getBPackageManager().getApplicationInfo(packageName,0, BActivityThread.getUserId());
            if(applicationInfo != null){
                return PackageManager.COMPONENT_ENABLED_STATE_DEFAULT;
            }
            if (AppSystemEnv.isOpenPackage(componentName)) {
                return method.invoke(who, args);
            }
            throw new IllegalArgumentException();
        }
    }
    
    @ProxyMethod("addPackageToPreferred")
    public static class addPackageToPreferred extends MethodHook {
        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            return 0;
        }
    }
    
    @ProxyMethod("setSplashScreenTheme")
    public static class SetSplashScreenTheme extends MethodHook {
        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            boolean isXiaomi = BuildCompat.isMIUI() || Build.MANUFACTURER.toLowerCase().contains("xiaomi") || Build.BRAND.toLowerCase().contains("xiaomi") || Build.DISPLAY.toLowerCase().contains("hyperos");
            if (isXiaomi) { return null; }
            try {
                return method.invoke(who, args);
            } catch (SecurityException ignored) {
                return null;
            } catch (Exception e) {
                if (e.getCause() instanceof SecurityException) {
                    return null;
                }
                throw e;
            }
        }
    }
    
    @ProxyMethod("checkSignatures")
    public static class checkSignatures extends MethodHook {
        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            if (args.length == 2) {
                if (args[0] instanceof String && args[1] instanceof String) {
                    String pkg1 = (String) args[0];
                    String pkg2 = (String) args[1];
                    PackageManager pm = MundoCore.getPackageManager();
                    try {
                        PackageInfo pkgInfo1 = pm.getPackageInfo(pkg1, PackageManager.GET_SIGNATURES);
                        PackageInfo pkgInfo2 = pm.getPackageInfo(pkg2, PackageManager.GET_SIGNATURES);
                        Signature[] sigs1 = pkgInfo1.signatures;
                        Signature[] sigs2 = pkgInfo2.signatures;
                        if (sigs1 == null || sigs1.length == 0) {
                            return (sigs2 == null || sigs2.length == 0) ? 1 : -1;
                        }
                        if (sigs2 == null || sigs2.length == 0) {
                            return -2;
                        }
                        return Arrays.equals(sigs1, sigs2) ? 0 : -3;
                    } catch (Exception e) {
                        // Fall through
                    }
                }
            }
            return method.invoke(who, args);
        }
    }
    
    @ProxyMethod("checkUidSignatures")
    public static class checkUidSignatures extends MethodHook {
        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            return 0;
        }
    }
    
    @ProxyMethod("clearPackagePersistentPreferredActivities")
    public static class clearPackagePersistentPreferredActivities extends MethodHook {
        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            MethodParameterUtils.replaceFirstAppPkg(args);
            return method.invoke(who, args);
        }
    }
    
    @ProxyMethod("clearPackagePreferredActivities")
    public static class clearPackagePreferredActivities extends MethodHook {
        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            MethodParameterUtils.replaceFirstAppPkg(args);
            return method.invoke(who, args);
        }
    }
    
    @ProxyMethod("getApplicationBlockedSettingAsUser")
    public static class getApplicationBlockedSettingAsUser extends MethodHook {
        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            MethodParameterUtils.replaceFirstAppPkg(args);
            return method.invoke(who, args);
        }
    }
    
    @ProxyMethod("setPackageStoppedState")
    public static class setPackageStoppedState extends MethodHook {
        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            MethodParameterUtils.replaceFirstAppPkg(args);
            return method.invoke(who, args);
        }
    }

    // hasSigningCertificate(String packageName, byte[] certificate, int type)
    // GMS SDK calls this to verify its own signing certificate. We return true for GMS.
    @ProxyMethod("hasSigningCertificate")
    public static class HasSigningCertificate extends MethodHook {
        @Override
        protected Object hook(Object who, Method method, Object[] args) throws Throwable {
            if (args.length >= 1 && GmsCore.GMS_PKG.equals(args[0])) {
                return true;
            }
            try {
                return method.invoke(who, args);
            } catch (Throwable ignored) {
                return false;
            }
        }
    }

}
