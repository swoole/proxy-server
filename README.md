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
php tcp-proxy.php
php http-proxy.php
```

Test
===
* Ubuntu 14.04 + inter I5 4 core + 8G Memory 

```shell
ab -c 1000 -n 1000000 -k http://127.0.0.1:9509/
ab -c 1000 -n 1000000 -k http://127.0.0.1:9509/
```

```shell
htf@htf-All-Series:~/workspace/proj/proxy-server$ ab -c 1000 -n 1000000 -k http://127.0.0.1:9509/
This is ApacheBench, Version 2.3 <$Revision: 1528965 $>
Copyright 1996 Adam Twiss, Zeus Technology Ltd, http://www.zeustech.net/
Licensed to The Apache Software Foundation, http://www.apache.org/

Benchmarking 127.0.0.1 (be patient)
Completed 100000 requests
Completed 200000 requests
Completed 300000 requests
Completed 400000 requests
Completed 500000 requests
Completed 600000 requests
Completed 700000 requests
Completed 800000 requests
Completed 900000 requests
Completed 1000000 requests
Finished 1000000 requests


Server Software:        nginx/1.4.6
Server Hostname:        127.0.0.1
Server Port:            9509

Document Path:          /
Document Length:        371 bytes

Concurrency Level:      1000
Time taken for tests:   11.347 seconds
Complete requests:      1000000
Failed requests:        0
Keep-Alive requests:    990487
Total transferred:      616952435 bytes
HTML transferred:       371000000 bytes
Requests per second:    88131.61 [#/sec] (mean)
Time per request:       11.347 [ms] (mean)
Time per request:       0.011 [ms] (mean, across all concurrent requests)
Transfer rate:          53098.64 [Kbytes/sec] received

Connection Times (ms)
              min  mean[+/-sd] median   max
Connect:        0    0   0.8      0      26
Processing:     0   11  26.6      8    1047
Waiting:        0   11  26.6      8    1047
Total:          0   11  26.9      8    1055

Percentage of the requests served within a certain time (ms)
  50%      8
  66%     12
  75%     14
  80%     16
  90%     22
  95%     27
  98%     34
  99%     39
 100%   1055 (longest request)
```
