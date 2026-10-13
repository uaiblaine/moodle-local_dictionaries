# search(): the substring group stops negating the prefix, so it only repeats prefix matches.
s~':notprefix', true, true, true\)~':notprefix', true, true, false)~s;
