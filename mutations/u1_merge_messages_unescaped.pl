# local_dictionaries_merge_codekey_collisions(): the merge messages print the raw codes.
s~ s\(\$item->code\)~ \$item->code~s;
s~ s\(\$keeper->code\)~ \$keeper->code~s;
