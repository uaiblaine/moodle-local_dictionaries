# search(): the parent code bound counts bytes, so a parent code of multibyte characters under the limit filters nothing.
s~core_text::strlen\(\$parentcode\) > self::MAX_QUERY_LENGTH~strlen(\$parentcode) > self::MAX_QUERY_LENGTH~s;
