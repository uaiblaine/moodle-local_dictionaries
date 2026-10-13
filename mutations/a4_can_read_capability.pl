# can_read(): a restricted domain is readable by everyone.
s~return has_capability\('local/dictionaries:view', context_system::instance\(\), \$userid\);~return true;~s;
