# Vtiger CRM Devel Setup

How to setup a devel VM for vtiger

## Set up the devel PC

The approach is, to have the code on the host, but mount ist via ssh into the vm. This way, you can easily access the files via the IDE of your choice without any sshfs involved.

### Code

Clone the repo into your Devel PCs home directory and setup a devel branch. 

```bash
cd ~/
git clone https://github.com/SteffenKockel/vtiger
git ceckout -b my_6.0.0
```

### Firewall

* Open port `22` - or whatever port you used to setup your ssh server - to accept connections at least from your devel vm. 

* Open port `9000` - or whatever port you used to setup xdebug - to accept connections at least from your devel vm. 

### IDE

Setup your IDE to accept debug connections from the VM. 

## Set up the virtual machine

Install a Linux of your choice with all needed packages mentioned in the [README](README.md) of this repo.

Also install for devel purposes

    sshfs php-xdebug git

Create a VHost and a Database as mentioned in the [README](README.md). 

edit the `/etc/fstab` and add a line that mounts the fs from the devel host into the VM.

```bash
sshfs#you@10.10.0.2:~/vtiger /var/www/vtiger fuse noauto,uid=apache,gid=apache,allow_other,umask=0007 0 0
```
The `umask` is important for vtiger when it comes to install tests, because vtiger creates a lot of folders and files during installation. Also, you want to go with `noauto`, unless you are using keyfile authentication. 
