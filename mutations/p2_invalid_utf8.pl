# CSV: invalid UTF-8 is no longer rejected.
s~if \(\$cell !== null && !mb_check_encoding\(\(string\)\$cell, 'UTF-8'\)\) \{~if (false) {~s;
