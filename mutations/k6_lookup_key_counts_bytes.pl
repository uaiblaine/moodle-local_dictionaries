# keys::lookup_key(): the bound counts bytes, so a code of multibyte characters under the limit gets no key.
s~core_text::strlen\(\$code\) > self::MAX_INPUT_LENGTH~strlen(\$code) > self::MAX_INPUT_LENGTH~s;
