<?php
namespace Deployer;

require 'recipe/composer.php';

set('repository', 'https://github.com/elseifab/id-kollen-dev.git');
set('git_tty', true);
set('shared_files', ['.env','web/.htaccess']);
set('shared_dirs', ['web/app/uploads']);

localhost()
    ->stage('development');

host('malwebb.se')
    ->port(22)
    ->set('deploy_path', '~/sites/idkollen.malwebb.se')
    ->user('malarda2')
    ->set('branch', 'master')
    ->stage('production')
    ->identityFile('~/.ssh/id_rsa');

// Tasks
task('wp-init', function () {
    $deploy_path = has('deploy_path') ? get('deploy_path') : null;
    $path = $deploy_path ? "cd {$deploy_path}/current && " : "";

    $host = run("{$path}wp config get --constant=WP_HOME");
    $password = uniqid('pass');

    run("{$path}wp core install --url={$host} --title=RootsBedrock --admin_user=root --admin_password={$password} --admin_email=root@example.se");

    writeln("WordPress installed with user 'root' and password '{$password}'");
})->desc('Initialize your local or production setup from scratch');

task('wp-cleanup', function () {
    $envFile = run("cd {{deploy_path}}/current && cat .env");

    if (!$envFile) {
        return;
    }

    $actions = [
        "/opt/cpanel/composer/bin/composer require elseif/id-kollen",
        "vendor/bin/wp language core install sv_SE",
        "vendor/bin/wp language core update",
        "vendor/bin/wp language core activate sv_SE",
        "vendor/bin/wp rewrite structure '/%postname%'",
        "vendor/bin/wp rewrite flush",
        "vendor/bin/wp option update timezone_string \"Europe/Stockholm\"",
        "vendor/bin/wp option update date_format \"Y-m-d\"",
        "vendor/bin/wp option update time_format \"H:i\"",
        "php -r \"opcache_reset();\"",
        "vendor/bin/wp cache flush",
    ];

    foreach ($actions as $action) {
        writeln($action);
        run("cd {{deploy_path}}/current && $action", [
            "timeout" => 999,
        ]);
    }
})->desc('After deploy, make WordPress clean for us');
after('deploy', 'wp-cleanup');

task('pull', function () {
    $host = Task\Context::get()->getHost();
    $user = $host->getUser();
    $hostname = $host->getHostname();

    $url = parse_url(run("cd {{deploy_path}}/current && wp config get --constant=WP_HOME"), PHP_URL_HOST);
    $localUrl = parse_url(runLocally("wp config get --constant=WP_HOME"), PHP_URL_HOST);

    $actions = [
        "ssh {$user}@{$hostname} 'cd {{deploy_path}}/current && wp db export - | gzip' > db.sql.gz",
        "gzip -df db.sql.gz",
        "vendor/bin/wp language core install sv_SE",
        "vendor/bin/wp db import db.sql",
        "rm -f db.sql",
        "vendor/bin/wp search-replace '{$url}' '{$localUrl}' --all-tables",
        "rsync --exclude .cache -re ssh " .
        "{$user}@{$hostname}:{{deploy_path}}/shared/web/app/uploads web/app",
        "vendor/bin/wp rewrite flush",
        "vendor/bin/wp cache flush",
        "vendor/bin/wp theme update --all"
    ];

    foreach ($actions as $action) {
        writeln("{$action}");
        writeln(runLocally($action, ['timeout' => 999]));
    }
})->desc('Get the production setup to your local dev env');