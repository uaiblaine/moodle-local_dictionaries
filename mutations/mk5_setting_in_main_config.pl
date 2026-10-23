# Store a setting in the main config table, without the component prefix.
s{(\n\s+'local/dictionaries:manage'\n\s+\)\);\n)}{$1    \$ADMIN->add('localplugins', new admin_setting_configcheckbox('local_dictionaries_enabled', 'x', 'x', 0));\n};
