# keys::lookup_key(): a caller's code of any length gets a key.
s~if \(core_text::strlen\(\$code\) > self::MAX_INPUT_LENGTH\) \{\n            return '';\n        \}\n~~s;
