# lookup(): accept every row the database returns, not only the keys that were asked for.
s~if \(!isset\(\$wanted\[\(string\)\$row->codekey\]\)\) \{~if (false) {~s;
