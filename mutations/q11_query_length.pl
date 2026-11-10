# search(): an over-long query is searched like any other.
s~core_text::strlen\(\$query\) > self::MAX_QUERY_LENGTH~false~s;
