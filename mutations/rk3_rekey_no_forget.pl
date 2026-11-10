# rekey_items() bumps the revision but leaves the cached domain record (with the old revision) in place.
s~(public function rekey_items\(\).*?)domains::forget\(\(string\)\$domain->shortname\);~$1;~s;
