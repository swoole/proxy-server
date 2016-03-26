# proxy-server
Full asynchronous proxy server can support over a large number of concurrent.

Install
=====
```shell
pecl install swoole
```

Run
====
```shell
php proxy.php
```

Test
===
```shell
ab -c 1000 -n 100000 -k http://127.0.0.1:9509/
```
