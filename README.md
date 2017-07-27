CheckEmail
==========

```php
require_once __DIR__ . '/vendor/autoload.php';
use CheckEmail\CheckEmail;

$domainsExcluded = include __DIR__ . '/domainsExcluded.php';
$domainsTemporary = include __DIR__ . '/domainsTemporary.php';

$email = 'info@ya.ru';
$ce = new CheckEmail($email);
$ce->addDomainTemporary($domainsTemporary)->addDomainExcluded($domainsExcluded);
if ($ce->check()) {
    echo $email . ' exists';
} else {
    echo $email . ' not exists';
}
```

With debug logs
```php
require_once __DIR__ . '/vendor/autoload.php';
use CheckEmail\CheckEmail;

$domainsExcluded = include __DIR__ . '/domainsExcluded.php';
$domainsTemporary = include __DIR__ . '/domainsTemporary.php';

$email = 'info@ya.ru';
$ce = new CheckEmail($email);
$ce->addDomainTemporary($domainsTemporary)->addDomainExcluded($domainsExcluded);
$ce->setDebug();
if ($ce->check()) {
    echo $email . ' exists';
} else {
    echo $email . ' not exists<br>';
    print_r($ce->getLogs());
}
```