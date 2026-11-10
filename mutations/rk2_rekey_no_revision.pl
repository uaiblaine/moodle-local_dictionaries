# rekey_items() rewrites the keys without bumping the domain revision.
s~(public function rekey_items\(\).*?)SET revision = revision \+ 1, timemodified = :now~$1SET timemodified = :now~s;
