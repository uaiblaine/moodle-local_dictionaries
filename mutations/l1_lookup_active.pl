# lookup(): every item reports itself active, so a consumer accepts a deactivated code.
s~'active' => \(int\)\$row->active === 1,~'active' => true,~s;
