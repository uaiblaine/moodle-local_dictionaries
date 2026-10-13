# Drop the revision from the label cache key: a write no longer invalidates cached labels.
s~'_' \. \$revision \. '_' \. sha1~'_' . sha1~s;
