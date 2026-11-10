# search(): an over-long parent code is searched like any other.
s~\(\$parentcode !== null && core_text::strlen\(\$parentcode\) > self::MAX_QUERY_LENGTH\)~false~s;
