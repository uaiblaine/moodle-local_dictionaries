# CSV: an over-long code is no longer rejected.
s~if \(core_text::strlen\(\$code\) > keys::MAX_KEY_LENGTH\) \{~if (false) {~s;
