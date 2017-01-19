https://ejz.ru/130/collect-your-etc-hosts

```bash
$ wget 'https://raw.githubusercontent.com/Ejz/Common/master/etc-hosts/hosts.txt'
$ cat hosts.txt | sudo tee -a /etc/hosts >/dev/null
$ rm -f hosts.txt
```
