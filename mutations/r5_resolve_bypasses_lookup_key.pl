# resolver::resolve_bulk(): keys are derived without the length bound.
s~\$codekey = keys::lookup_key\(\$code\);~\$codekey = keys::code_key(keys::clean_code(\$code));~s;
