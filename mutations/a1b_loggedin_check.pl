# require_read(): an open domain stops refusing a request with no logged-in user.
s~if \(!isloggedin\(\) \|\| isguestuser\(\)\) \{(\n\s+throw)~if (isguestuser()) {$1~s;
