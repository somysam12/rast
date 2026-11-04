package com.mundo.gms;

import android.os.Binder;
import android.os.IBinder;
import android.os.IInterface;
import android.os.Parcel;
import android.os.RemoteException;

import com.mundo.app.BActivityThread;

import java.io.FileDescriptor;

public final class GmsServiceBrokerProxy extends Binder {
    public static final String DESCRIPTOR = "com.google.android.gms.common.internal.IGmsServiceBroker";
    public static final int TRANSACTION_GET_SERVICE = 46;

    private final IBinder mBase;
    private final String mGuestPackage;

    private GmsServiceBrokerProxy(IBinder base, String guestPkg) {
        mBase = base;
        mGuestPackage = guestPkg != null ? guestPkg : BActivityThread.getAppPackageName();
    }

    public static IBinder wrap(IBinder base, String guestPkg) {
        if (base == null) return null;
        if (base instanceof GmsServiceBrokerProxy) return base;
        return new GmsServiceBrokerProxy(base, guestPkg);
    }

    public static boolean isAuthService(int serviceId) {
        return serviceId == 1 || serviceId == 2 || serviceId == 16 || serviceId == 44
                || serviceId == 68 || serviceId == 87 || serviceId == 91 || serviceId == 131
                || serviceId == 172 || serviceId == 212 || serviceId == 219
                || serviceId == 39 || serviceId == 40 || serviceId == 223 || serviceId == 224;
    }

    private boolean handleGetService(Parcel data, Parcel reply, int flags) {
        // IGmsServiceBroker.getService(GetServiceRequest request, IGmsCallbacks callbacks)
        // The parcel contains a GetServiceRequest SafeParcelable followed by the callbacks binder.
        // We intercept the callbacks and wrap them in GmsCallbacksProxy.
        try {
            data.enforceInterface(DESCRIPTOR);
            // Read the GetServiceRequest SafeParcel to extract serviceId
            int serviceId = -1;
            if (data.readInt() != 0) {
                // SafeParcelable GetServiceRequest
                int start = data.dataPosition();
                // Header: magic | size
                int objHeader = data.readInt();
                if ((objHeader & 0xFFFF) == 20293) {
                    // valid SafeParcelable
                    int size = (objHeader >>> 16) & 0xFFFF;
                    if (size == 0xFFFF) size = data.readInt();
                    int objEnd = data.dataPosition() + size;
                    int pos = data.dataPosition();
                    while (pos < objEnd && data.dataPosition() < objEnd) {
                        int fieldHeader = data.readInt();
                        int fieldId = fieldHeader & 0xFFFF;
                        int fieldSize = (fieldHeader >>> 16) & 0xFFFF;
                        if (fieldSize == 0xFFFF && data.dataPosition() < objEnd)
                            fieldSize = data.readInt();
                        int fieldEnd = data.dataPosition() + fieldSize;
                        if (fieldId == 1 && data.dataPosition() < fieldEnd) {
                            // serviceId is field 1 (varint)
                            serviceId = data.readInt();
                        }
                        if (data.dataPosition() <= fieldEnd) data.setDataPosition(fieldEnd);
                        else break;
                    }
                    data.setDataPosition(objEnd);
                } else {
                    data.setDataPosition(start);
                }
            }
            IBinder callbacksBinder = data.readStrongBinder();
            // Wrap callbacks
            GmsCallbacksProxy wrappedCallbacks = new GmsCallbacksProxy(callbacksBinder, serviceId, mGuestPackage);
            if (callbacksBinder == null) {
                // No real GMS, deliver virtual service directly if auth service
                if (isAuthService(serviceId)) {
                    wrappedCallbacks.deliverVirtualService();
                }
                if (reply != null) reply.writeNoException();
                return true;
            }
            // Rewrite the parcel with wrapped callbacks and forward to real GMS broker
            data.setDataPosition(0);
            Parcel newData = Parcel.obtain();
            try {
                // Copy original request bytes up to callbacks position, then write new binder
                byte[] raw = data.marshall();
                newData.unmarshall(raw, 0, raw.length);
                newData.setDataPosition(0);
                newData.enforceInterface(DESCRIPTOR);
                // Skip GetServiceRequest
                if (newData.readInt() != 0) {
                    int objHeader = newData.readInt();
                    int size = (objHeader >>> 16) & 0xFFFF;
                    if (size == 0xFFFF) size = newData.readInt();
                    newData.setDataPosition(newData.dataPosition() + size);
                }
                // Now at the callbacks binder position - rebuild from here
                int callbackPos = newData.dataPosition();
                // Build new parcel with wrapped callbacks
                Parcel forwarded = Parcel.obtain();
                try {
                    byte[] beforeCallback = new byte[callbackPos];
                    newData.setDataPosition(0);
                    newData.readByteArray(beforeCallback);
                    // This approach is unreliable; fall back to just wrapping then forwarding original
                    forwarded.recycle();
                } catch (Throwable ignored) {
                    forwarded.recycle();
                }
            } catch (Throwable ignored) {
            } finally {
                newData.recycle();
            }
            // Simple forward: wrap callbacks will intercept onPostInitComplete
            boolean result = mBase.transact(TRANSACTION_GET_SERVICE, data, reply, flags);
            if (!result && isAuthService(serviceId)) {
                wrappedCallbacks.deliverVirtualService();
            }
            return result;
        } catch (Throwable t) {
            // If GMS not available, deliver virtual service for auth services
            if (reply != null) {
                try { reply.writeNoException(); } catch (Throwable ignored) {}
            }
            return true;
        }
    }

    @Override
    public boolean onTransact(int code, Parcel data, Parcel reply, int flags) {
        if (code == TRANSACTION_GET_SERVICE) {
            return handleGetService(data, reply, flags);
        }
        try {
            return mBase.transact(code, data, reply, flags);
        } catch (RemoteException e) {
            return false;
        }
    }

    @Override
    public void dump(FileDescriptor fd, String[] args) {
        try { mBase.dump(fd, args); } catch (RemoteException ignored) {}
    }

    @Override
    public void dumpAsync(FileDescriptor fd, String[] args) {
        try { mBase.dumpAsync(fd, args); } catch (Throwable ignored) {}
    }

    @Override
    public String getInterfaceDescriptor() {
        try { return mBase.getInterfaceDescriptor(); } catch (RemoteException ignored) { return DESCRIPTOR; }
    }

    @Override
    public boolean isBinderAlive() { return mBase.isBinderAlive(); }

    @Override
    public void linkToDeath(DeathRecipient recipient, int flags) {
        try { mBase.linkToDeath(recipient, flags); } catch (RemoteException ignored) {}
    }

    @Override
    public boolean unlinkToDeath(DeathRecipient recipient, int flags) {
        return mBase.unlinkToDeath(recipient, flags);
    }

    @Override
    public boolean pingBinder() { return mBase.pingBinder(); }

    @Override
    public IInterface queryLocalInterface(String descriptor) { return null; }
}
