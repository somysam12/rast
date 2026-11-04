package com.mundo.gms;

import android.accounts.Account;
import android.os.Binder;
import android.os.Bundle;
import android.os.IBinder;
import android.os.Parcel;
import android.util.Log;

public final class GmsVirtualAuthService extends Binder {
    public static final String DESCRIPTOR_ACCOUNT_ACCESSOR = "com.google.android.gms.common.internal.IAccountAccessor";
    public static final String DESCRIPTOR_AUTH = "com.google.android.gms.auth.api.internal.IAuthService";
    public static final String DESCRIPTOR_COMMON = "com.google.android.gms.common.internal.service.ICommonService";
    public static final String DESCRIPTOR_CREDENTIALS = "com.google.android.gms.auth.api.credentials.internal.ICredentialsService";
    public static final String DESCRIPTOR_GOOGLE_SIGN_IN = "com.google.android.gms.auth.api.signin.internal.ISignInService";
    public static final String DESCRIPTOR_GOOGLE_SIGN_IN_CALLBACKS = "com.google.android.gms.auth.api.signin.internal.ISignInCallbacks";
    public static final String DESCRIPTOR_SIGN_IN = "com.google.android.gms.signin.internal.ISignInService";
    public static final String DESCRIPTOR_SIGN_IN_CALLBACKS = "com.google.android.gms.signin.internal.ISignInCallbacks";

    private final int mServiceId;
    private final String mCallingPackage;

    public static final class AccountAccessorStub extends Binder {
        private final Account mAccount;

        public AccountAccessorStub(Account account) {
            mAccount = account;
            attachInterface(null, DESCRIPTOR_ACCOUNT_ACCESSOR);
        }

        @Override
        public boolean onTransact(int code, Parcel data, Parcel reply, int flags) {
            if (code == 2) {
                if (reply != null) {
                    reply.writeNoException();
                    if (mAccount != null) {
                        reply.writeInt(1);
                        mAccount.writeToParcel(reply, 0);
                    } else {
                        reply.writeInt(0);
                    }
                }
                return true;
            }
            return super.onTransact(code, data, reply, flags);
        }
    }

    private GmsVirtualAuthService(int serviceId, String callerPkg) {
        mServiceId = serviceId;
        mCallingPackage = callerPkg;
        attachInterface(null, getDescriptorForService(serviceId));
    }

    public static IBinder create(int serviceId, String callerPkg) {
        if (serviceId != 91 && serviceId != 1) {
            throw new IllegalArgumentException("Unsupported virtual GMS service: " + serviceId);
        }
        return new GmsVirtualAuthService(serviceId, callerPkg);
    }

    private static String getDescriptorForService(int id) {
        switch (id) {
            case 16: return DESCRIPTOR_AUTH;
            case 40: return DESCRIPTOR_CREDENTIALS;
            case 44: return DESCRIPTOR_SIGN_IN;
            case 68: return DESCRIPTOR_CREDENTIALS;
            case 91: return DESCRIPTOR_GOOGLE_SIGN_IN;
            case 131: return DESCRIPTOR_SIGN_IN;
            case 172: return DESCRIPTOR_AUTH;
            default: return DESCRIPTOR_COMMON;
        }
    }

    private boolean handleAuthTransaction(int code, Parcel data, Parcel reply) {
        if (reply != null) {
            reply.writeNoException();
            Bundle b = new Bundle();
            b.putString("Error", "ServiceUnavailable");
            b.putInt("errorCode", 6);
            b.putString("errorMessage", "Google authorization service is unavailable");
            reply.writeInt(1);
            b.writeToParcel(reply, 0);
        }
        return true;
    }

    private boolean handleCommonTransaction(int code, Parcel data, Parcel reply) {
        try {
            data.enforceInterface(DESCRIPTOR_COMMON);
            IBinder callbacks = data.readStrongBinder();
            if (callbacks != null) {
                Parcel req = Parcel.obtain();
                Parcel rep = Parcel.obtain();
                try {
                    req.writeInterfaceToken("com.google.android.gms.common.internal.service.ICommonCallbacks");
                    req.writeInt(0);
                    callbacks.transact(1, req, rep, 0);
                    rep.readException();
                    rep.recycle();
                } catch (Throwable ignored) {
                    rep.recycle();
                }
                req.recycle();
            }
        } catch (Throwable ignored) {}
        if (reply != null) reply.writeNoException();
        return true;
    }

    private boolean handleGoogleSignInTransaction(int code, Parcel data, Parcel reply) {
        Log.i(GmsBridge.TAG, "GmsVirtualAuthService.handleGoogleSignInTransaction: code=" + code);
        // Transaction 1: getSignInHubActivity - return failure, let GmsSignInBridgeActivity handle it
        // Transaction 2: getCurrentAccount - return empty
        // Other: return not supported
        if (reply != null) {
            reply.writeNoException();
            if (code == 1 || code == 2) {
                // Return empty/null result to trigger the sign-in flow via startActivity
                reply.writeInt(0);
            }
        }
        return true;
    }

    private boolean handleSignInTransaction(int code, Parcel data, Parcel reply) {
        if (code == 12) {
            try {
                data.enforceInterface(DESCRIPTOR_SIGN_IN);
                if (data.readInt() != 0) {
                    // skip SafeParcelable header
                    data.setDataPosition(data.dataPosition() + (data.readInt() >> 16) - 4);
                }
                sendSignInResponse(data.readStrongBinder());
                if (reply != null) reply.writeNoException();
                return true;
            } catch (Throwable t) {
                Log.w(GmsBridge.TAG, "handleSignInTransaction code 12 error", t);
            }
        }
        if (reply != null) reply.writeNoException();
        return true;
    }

    private void sendSignInResponse(IBinder callbacks) {
        if (callbacks == null) return;
        Account[] accounts = GmsBridge.getRealGoogleAccounts();
        Account account = (accounts != null && accounts.length > 0)
                ? accounts[0] : new Account("user@gmail.com", GmsBridge.GOOGLE_ACCOUNT_TYPE);
        Log.i(GmsBridge.TAG, "sendSignInResponse: delivering SignInResponse for " + account.name);
        Parcel data = Parcel.obtain();
        Parcel reply = Parcel.obtain();
        try {
            data.writeInterfaceToken(DESCRIPTOR_SIGN_IN_CALLBACKS);
            int headerPos = data.dataPosition();
            data.writeInt(65535);
            data.writeInt(262145); data.writeInt(1);
            data.writeInt(262146); data.writeInt(0);
            data.writeInt(3); data.writeInt(0);
            data.writeInt(4); data.writeInt(0);
            data.writeInt(3);
            data.writeInt(262145); data.writeInt(1);
            AccountAccessorStub stub = new AccountAccessorStub(account);
            data.writeInt(2); data.writeStrongBinder(stub);
            data.writeInt(3);
            data.writeInt(262145); data.writeInt(1);
            data.writeInt(262146); data.writeInt(0);
            data.writeInt(262148); data.writeInt(1);
            data.writeInt(262149); data.writeInt(1);
            int endPos = data.dataPosition();
            data.setDataPosition(headerPos);
            data.writeInt(((endPos - headerPos) << 16) | 65535);
            data.setDataPosition(endPos);
            try {
                callbacks.transact(8, data, reply, 0);
                reply.readException();
            } catch (Throwable ignored) {
                data.setDataPosition(0);
                data.writeInterfaceToken(DESCRIPTOR_SIGN_IN_CALLBACKS);
                callbacks.transact(1, data, reply, 0);
            }
        } finally {
            try { reply.recycle(); } finally { data.recycle(); }
        }
    }

    @Override
    public boolean onTransact(int code, Parcel data, Parcel reply, int flags) {
        if (code == 1598968902) return super.onTransact(code, data, reply, flags);
        Log.i(GmsBridge.TAG, "GmsVirtualAuthService.onTransact: serviceId=" + mServiceId + " code=" + code);
        switch (mServiceId) {
            case 91: return handleGoogleSignInTransaction(code, data, reply);
            case 44: case 131: return handleSignInTransaction(code, data, reply);
            case 16: case 172: return handleAuthTransaction(code, data, reply);
            case 1: return handleCommonTransaction(code, data, reply);
            default:
                if (reply != null) reply.writeNoException();
                return true;
        }
    }
}
