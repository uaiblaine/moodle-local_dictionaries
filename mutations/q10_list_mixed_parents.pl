# list_domains(): parents in more than one domain no longer give null (the first one wins).
s~count\(\$parents\) === 1~count(\$parents) >= 1~s;
