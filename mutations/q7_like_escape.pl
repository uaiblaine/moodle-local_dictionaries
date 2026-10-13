# search(): the query reaches LIKE unescaped, so '%' and '_' become wildcards.
s~\$escaped = \$DB->sql_like_escape\(\$searchkey\);~\$escaped = \$searchkey;~s;
