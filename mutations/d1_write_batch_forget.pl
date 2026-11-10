# write_batch() no longer drops the cached domain record (it carries the revision).
s~(private function write_batch\(.*?)domains::forget\(\(string\)\$domain->shortname\);~$1;~s;
