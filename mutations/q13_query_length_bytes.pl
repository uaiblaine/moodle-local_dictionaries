# search(): the query bound counts bytes, so a query of multibyte characters under the limit is not searched.
s~core_text::strlen\(\$query\) > self::MAX_QUERY_LENGTH~strlen(\$query) > self::MAX_QUERY_LENGTH~s;
