# The upgrade step no longer rewrites the stored keys.
s~\(new \\local_dictionaries\\local\\manager\(\)\)->rekey_items\(\);~~s;
