package com.mundo.gms;

import android.accounts.Account;
import android.net.Uri;
import android.os.Parcel;
import android.os.Parcelable;
import java.lang.reflect.Field;
import java.util.ArrayList;
import java.util.List;

public final class GmsSignInParcelCodec {

    public static final class Options {
        public Account account;
        public boolean forceCodeForRefreshToken;
        public boolean idTokenRequested;
        public final List<String> scopes = new ArrayList<>();
        public boolean serverAuthCodeRequested;
        public String serverClientId;
    }

    private GmsSignInParcelCodec() {}

    private static int begin(Parcel p, int id) {
        p.writeInt(id | (-65536));
        p.writeInt(0);
        return p.dataPosition();
    }

    private static void end(Parcel p, int start) {
        int pos = p.dataPosition();
        p.setDataPosition(start - 4);
        p.writeInt(pos - start);
        p.setDataPosition(pos);
    }

    private static Parcelable create(Class<?> cls, Parcel p) throws Exception {
        Field f = cls.getField("CREATOR");
        f.setAccessible(true);
        Parcelable.Creator<?> creator = (Parcelable.Creator<?>) f.get(null);
        p.setDataPosition(0);
        return (Parcelable) creator.createFromParcel(p);
    }

    private static int fieldEnd(Parcel p, int header, int limit) {
        int size = (header >>> 16) & 0xFFFF;
        if (size == 0xFFFF) {
            if (limit - p.dataPosition() >= 4) size = p.readInt();
            else throw new IllegalArgumentException("Truncated field size");
        }
        int pos = p.dataPosition();
        if (size >= 0 && size <= limit - pos) return pos + size;
        throw new IllegalArgumentException("Invalid SafeParcelable field size");
    }

    private static int objectEnd(Parcel p, int limit) {
        if (limit - p.dataPosition() < 8) throw new IllegalArgumentException("Truncated SafeParcelable");
        int header = p.readInt();
        if ((header & 0xFFFF) != 20293) throw new IllegalArgumentException("Invalid SafeParcelable header");
        return fieldEnd(p, header, limit);
    }

    private static void integer(Parcel p, int id, int v) {
        p.writeInt(id | 262144);
        p.writeInt(v);
    }

    private static void string(Parcel p, int id, String s) {
        if (s == null) return;
        int start = begin(p, id);
        p.writeString(s);
        end(p, start);
    }

    public static Parcelable googleAccount(Class<?> cls, String id, String idToken,
            String email, String displayName, String givenName, String familyName,
            Uri photoUrl, String serverAuthCode, long expiration, String obfuscatedId,
            List<?> grantedScopes) throws Exception {
        Parcel p = Parcel.obtain();
        try {
            int start = begin(p, 20293);
            integer(p, 1, 3);
            string(p, 2, id);
            string(p, 3, idToken);
            string(p, 4, email);
            string(p, 5, displayName);
            int scopeCount = 0;
            if (photoUrl != null) {
                int uriStart = begin(p, 6);
                photoUrl.writeToParcel(p, 0);
                end(p, uriStart);
            }
            string(p, 7, serverAuthCode);
            p.writeInt(524296); // field 8 varint
            p.writeLong(expiration);
            string(p, 9, obfuscatedId);
            int listStart = begin(p, 10);
            if (grantedScopes != null) scopeCount = grantedScopes.size();
            p.writeInt(scopeCount);
            if (grantedScopes != null) {
                for (Object scope : grantedScopes) {
                    p.writeInt(1);
                    int scopeStart = begin(p, 20293);
                    integer(p, 1, 1);
                    string(p, 2, scope.toString());
                    end(p, scopeStart);
                }
            }
            end(p, listStart);
            string(p, 11, givenName);
            string(p, 12, familyName);
            end(p, start);
            Parcelable result = create(cls, p);
            p.recycle();
            return result;
        } catch (Throwable t) {
            p.recycle();
            throw t;
        }
    }

    public static Parcelable signInAccount(Class<?> cls, String obfuscatedId,
            Parcelable account, String accountType) throws Exception {
        Parcel p = Parcel.obtain();
        try {
            int start = begin(p, 20293);
            string(p, 4, obfuscatedId);
            int acctStart = begin(p, 7);
            account.writeToParcel(p, 0);
            end(p, acctStart);
            string(p, 8, accountType);
            end(p, start);
            Parcelable result = create(cls, p);
            p.recycle();
            return result;
        } finally {
            p.recycle();
        }
    }

    public static Parcelable status(Class<?> cls, int statusCode, String message) throws Exception {
        Parcel p = Parcel.obtain();
        try {
            GmsBridge.writeSafeParcelStatus(p, statusCode, message);
            Parcelable result = create(cls, p);
            p.recycle();
            return result;
        } finally {
            p.recycle();
        }
    }

    public static Options readOptions(Parcelable parcelable, boolean isConfig) {
        Parcel p = Parcel.obtain();
        try {
            parcelable.writeToParcel(p, 0);
            p.setDataPosition(0);
            if (isConfig) {
                int objEnd = objectEnd(p, p.dataSize());
                while (p.dataPosition() < objEnd) {
                    int header = p.readInt();
                    int fEnd = fieldEnd(p, header, objEnd);
                    if ((header & 0xFFFF) == 5 && p.dataPosition() < fEnd) {
                        return readOptions(p, fEnd);
                    }
                    p.setDataPosition(fEnd);
                }
                throw new IllegalArgumentException("Sign-in configuration has no options");
            }
            return readOptions(p, p.dataSize());
        } finally {
            p.recycle();
        }
    }

    public static Options readOptions(Parcel p) {
        return readOptions(p, p.dataSize());
    }

    private static Options readOptions(Parcel p, int limit) {
        Options opts = new Options();
        int objEnd = objectEnd(p, limit);
        while (p.dataPosition() < objEnd) {
            int header = p.readInt();
            int fEnd = fieldEnd(p, header, objEnd);
            if (p.dataPosition() < fEnd) {
                switch (header & 0xFFFF) {
                    case 2:
                        int count = p.readInt();
                        if (count < 0 || count > (fEnd - p.dataPosition()) / 4)
                            throw new IllegalArgumentException("Invalid sign-in scope count");
                        for (int i = 0; i < count; i++) {
                            if (p.readInt() != 0) {
                                int scopeObjEnd = objectEnd(p, fEnd);
                                while (p.dataPosition() < scopeObjEnd) {
                                    int sh = p.readInt();
                                    int sfEnd = fieldEnd(p, sh, scopeObjEnd);
                                    if ((sh & 0xFFFF) == 2) {
                                        String s = p.readString();
                                        if (s != null && !opts.scopes.contains(s)) opts.scopes.add(s);
                                    }
                                    if (p.dataPosition() <= sfEnd) p.setDataPosition(sfEnd);
                                    else throw new IllegalArgumentException("Invalid scope field");
                                }
                            }
                        }
                        break;
                    case 3:
                        opts.account = Account.CREATOR.createFromParcel(p);
                        break;
                    case 4:
                        opts.idTokenRequested = p.readInt() != 0;
                        break;
                    case 5:
                        opts.serverAuthCodeRequested = p.readInt() != 0;
                        break;
                    case 6:
                        opts.forceCodeForRefreshToken = p.readInt() != 0;
                        break;
                    case 7:
                        opts.serverClientId = p.readString();
                        break;
                }
            }
            if (p.dataPosition() <= fEnd) p.setDataPosition(fEnd);
            else throw new IllegalArgumentException("Invalid sign-in option field");
        }
        return opts;
    }
}
