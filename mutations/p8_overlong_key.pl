# parse_row(): only the code's length is checked, not its key's.
s~if \(!keys::code_fits\(\$code, keys::code_key\(\$code\)\)\) \{~if (core_text::strlen(\$code) > keys::MAX_KEY_LENGTH) {~s;
