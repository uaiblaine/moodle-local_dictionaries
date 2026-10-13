# lookup(): the parent is never reported, so a consumer cannot check a dependent column.
s~\$parent = empty\(\$row->parentid\) \? null : \(\$parents\[\(int\)\$row->parentid\] \?\? null\);~\$parent = null;~s;
