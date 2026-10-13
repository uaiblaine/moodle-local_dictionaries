# search(): the prefix group compares for equality, so it can match nothing; Campinas for 'camp' is lost
# because the substring group excludes prefix matches.
s~\$base \. ' AND ' \. \$DB->sql_like\('i\.searchkey', ':prefix'\),~\$base . ' AND i.searchkey = :prefix',~s;
