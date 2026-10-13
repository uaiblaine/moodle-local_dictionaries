# search(): the parent join matches the item to itself instead of to its parent.
# Anchored on "$join =": list_domains() has a LEFT JOIN with the same tail, which comes first in the file.
s~(\$join = 'JOIN \{' \. domains::ITEM_TABLE \. '\} p ON p\.id = )i\.parentid~$1i.id~s;
