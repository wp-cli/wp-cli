<?php return array(
    'root' => array(
        'name' => 'acme/root-package',
        'pretty_version' => 'dev-main',
        'version' => 'dev-main',
        'reference' => null,
        'type' => 'project',
        'install_path' => __DIR__ . '/../../../',
        'aliases' => array(),
        'dev' => false,
    ),
    'versions' => array(
        'acme/bundle-package' => array(
            'pretty_version' => '1.0.0',
            'version' => '1.0.0.0',
            'reference' => null,
            'type' => 'library',
            'install_path' => __DIR__ . '/../acme/bundle-package',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
