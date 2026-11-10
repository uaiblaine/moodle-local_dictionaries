# parse_row(): an over-long code is no longer rejected.
s~if \(!keys::code_fits\(\$code, keys::code_key\(\$code\)\)\) \{~if (false) {~s;
