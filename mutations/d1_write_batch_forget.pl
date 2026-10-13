# write_batch() no longer drops the cached domain record (it carries the revision).
s~domains::forget\(\(string\)\$domain->shortname\);~;~s;
