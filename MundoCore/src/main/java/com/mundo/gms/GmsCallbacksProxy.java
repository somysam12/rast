package com.mundo.gms;

import android.os.Binder;
import android.os.Bundle;
import android.os.IBinder;
import android.os.Parcel;
import android.util.Log;

public final class GmsCallbacksProxy extends Binder {
    public static final String DESCRIPTOR = "com.google.android.gms.common.internal.IGmsCallbacks";
    public static final int TRANSACTION_ON_ACCOUNT_VALIDATION_COMPLETE = 2;
    public static final int TRANSACTION_ON_POST_INIT_COMPLETE = 1;
    public static final int TRANSACTION_ON_POST_INIT_COMPLETE_WITH_CONNECTION_INFO = 3;

    private final IBinder mOriginalCallbacks;
    private final int mServiceId;
    private final String mCallingPackage;

    public GmsCallbacksProxy(IBinder original, int serviceId, String callerPkg) {
        this.mOriginalCallbacks = original;
        this.mServiceId = serviceId;
        this.mCallingPackage = callerPkg;
    }

    private void deliverToOriginalCallbacks(int code, int statusCode, IBinder binder, Bundle extras) {
        if (mOriginalCallbacks == null) return;
        Parcel data = Parcel.obtain();
        Parcel reply = Parcel.obtain();
        try {
            data.writeInterfaceToken(DESCRIPTOR);
            data.writeInt(statusCode);
            data.writeStrongBinder(binder);
            if (extras != null) {
                data.writeInt(1);
                extras.writeToParcel(data, 0);
            } else {
                data.writeInt(0);
            }
            mOriginalCallbacks.transact(code, data, reply, 0);
            reply.readException();
        } catch (Throwable t) {
            Log.e(GmsBridge.TAG, "deliverToOriginalCallbacks failed", t);
        } finally {
            reply.recycle();
            data.recycle();
        }
    }

    private boolean forwardTransaction(int code, Parcel parcel) {
        if (mOriginalCallbacks == null) return false;
        Parcel reply = Parcel.obtain();
        try {
            parcel.setDataPosition(0);
            mOriginalCallbacks.transact(code, parcel, reply, 0);
            reply.readException();
            reply.recycle();
            return true;
        } catch (Throwable ignored) {
            reply.recycle();
            return false;
        }
    }

    private void handlePostInitComplete(int statusCode, IBinder binder, Bundle extras) {
        Log.i(GmsBridge.TAG, "GmsCallbacksProxy.onPostInitComplete: serviceId=" + mServiceId
                + " statusCode=" + statusCode + " hasBinder=" + (binder != null));
        if (statusCode == 0 && binder != null) {
            deliverToOriginalCallbacks(TRANSACTION_ON_POST_INIT_COMPLETE, statusCode, binder, extras);
            return;
        }
        if (GmsServiceBrokerProxy.isAuthService(mServiceId)) {
            Log.w(GmsBridge.TAG, "Real GMS rejected serviceId=" + mServiceId
                    + " (status=" + statusCode + "); substituting Virtual GMS Service");
            deliverVirtualService();
        } else {
            deliverToOriginalCallbacks(TRANSACTION_ON_POST_INIT_COMPLETE, statusCode, binder, extras);
        }
    }

    public void deliverVirtualService() {
        int id = mServiceId;
        if (id != 91 && id != 1) {
            deliverToOriginalCallbacks(TRANSACTION_ON_POST_INIT_COMPLETE, 10, null, null);
        } else {
            deliverToOriginalCallbacks(TRANSACTION_ON_POST_INIT_COMPLETE, 0,
                    GmsVirtualAuthService.create(id, mCallingPackage), null);
        }
    }

    @Override
    public boolean onTransact(int code, Parcel parcel, Parcel reply, int flags) {
        Bundle extras = null;
        if (code == TRANSACTION_ON_POST_INIT_COMPLETE) {
            parcel.enforceInterface(DESCRIPTOR);
            int statusCode = parcel.readInt();
            IBinder binder = parcel.readStrongBinder();
            if (parcel.readInt() != 0) {
                try { extras = Bundle.CREATOR.createFromParcel(parcel); } catch (Throwable ignored) {}
            }
            handlePostInitComplete(statusCode, binder, extras);
            if (reply != null) reply.writeNoException();
            return true;
        }
        if (code == TRANSACTION_ON_POST_INIT_COMPLETE_WITH_CONNECTION_INFO) {
            parcel.enforceInterface(DESCRIPTOR);
            int statusCode = parcel.readInt();
            IBinder binder = parcel.readStrongBinder();
            if (statusCode == 0 && binder != null) {
                forwardTransaction(code, parcel);
            } else {
                handlePostInitComplete(statusCode, binder, null);
            }
            if (reply != null) reply.writeNoException();
            return true;
        }
        return forwardTransaction(code, parcel);
    }
}
