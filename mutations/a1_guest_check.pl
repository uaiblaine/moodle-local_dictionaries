# require_read(): an open domain stops refusing the guest user.
s~if \(!isloggedin\(\) \|\| isguestuser\(\)\) \{(\n\s+throw)~if (!isloggedin()) {$1~s;
