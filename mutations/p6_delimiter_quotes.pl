# CSV: delimiter detection counts delimiters inside quoted fields.
s~\$inquotes = !\$inquotes;~\$inquotes = false;~s;
