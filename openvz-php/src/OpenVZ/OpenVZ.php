<?php

namespace OpenVZ;

class OpenVZ {

    protected static $ssh;

    public function __construct($connection){
        if(!$connection->exec('uptime')) {
            throw new Exception('SSH connection not provided');
        }
        self::$ssh = $connection;
    }

    public static function vcreate($params){
        $commands = "/usr/sbin/vzctl create {$params->ctid} --ostemplate {$params->os} --config basic --hostname {$params->hostname}
            /usr/sbin/vzctl set {$params->ctid} --diskspace {$params->disk}g:{$params->disk}g --save
            /usr/sbin/vzctl set {$params->ctid} --diskinodes {$params->inodes}:{$params->inodes} --save
            /usr/sbin/vzctl set $params->ctid  --vmguarpages {$params->ram}M --oomguarpages {$params->ram}M --privvmpages {$params->ram}M:{$params->burst}M --swap
            {$params->swap}M --save
            /usr/sbin/vzctl set {$params->ctid} --nameserver {$params->dns1}  --nameserver {$params->dns2} --save
            /usr/sbin/vzctl set {$params->ctid} --userpasswd root:{$params->password} --save
            /usr/sbin/vzctl set {$params->ctid} --onboot yes --save
            /usr/sbin/vzctl set {$params->ctid} --cpuunits {$params->cpu_units} --save
            /usr/sbin/vzctl set {$params->ctid} --cpulimit {$params->cpu_limit} --cpus {$params->cpus} --save
            modprobe iptables_module ipt_helper ipt_REDIRECT ipt_TCPMSS ipt_LOG ipt_TOS iptable_nat ipt_MASQUERADE ipt_multiport xt_multiport ipt_state xt_state ipt_limit xt_limit ipt_recent xt_connlimit ipt_owner xt_owner iptable_nat ipt_DNAT iptable_nat ipt_REDIRECT ipt_length ipt_tcpmss iptable_mangle ipt_tos iptable_filter ipt_helper ipt_tos ipt_ttl ipt_SAME ipt_REJECT ipt_helper ipt_owner ip_tables
            /usr/sbin/vzctl set {$params->ctid} --iptables ipt_REJECT --iptables ipt_tos --iptables ipt_TOS --iptables ipt_LOG --iptables ip_conntrack --iptables ipt_limit --iptables ipt_multiport --iptables iptable_filter --iptables iptable_mangle --iptables ipt_TCPMSS --iptables ipt_tcpmss --iptables ipt_ttl --iptables ipt_length --iptables ipt_state --iptables iptable_nat --iptables ip_nat_ftp --save
            /usr/sbin/vzctl start {$params->ctid}";
        return self::$ssh->exec($commands);
    }

    public static function vdestroy($ctid){
        $commands = "/usr/sbin/vzctl stop {$ctid}
            /usr/sbin/vzctl destroy {$ctid}";
        return self::$ssh->exec($commands);
    }

    public static function vresize($params){
        $vdisk = self::$ssh->exec("/usr/sbin/vzlist {$params->ctid} -Ho diskspace");
        if(($params->disk*1024*1024) > $vdisk){
            $commands = "/usr/sbin/vzctl set {$params->ctid} --ram {$params->ram}M --swap {$params->swap}M --save;
                /usr/sbin/vzctl set {$params->ctid} --cpuunits {$params->cpuu} --save;
                /usr/sbin/vzctl set {$params->ctid} --cpulimit {$params->cpul} --cpus {$params->cpus} --save;
                /usr/sbin/vzctl set {$params->ctid} --diskspace {$params->disk}G:{$params->disk}G --save;
                /usr/sbin/vzctl set {$params->ctid} --diskinodes {$params->inodes}:{$params->inodes} --save;";
            return self::$ssh->exec($commands);
        }
        return "New disk size cannot be less than current disk size!";
    }

    public static function vaddip($params){
        if(isset($params->ips) && count($params->ips) > 1) {
            $commands = "";
            foreach($params->ips as $ip){
                $commands .= "/usr/sbin/vzctl set {$params->ctid} --ipadd {$ip} --save";
            }
        } else {
            $commands = "/usr/sbin/vzctl set {$params->ctid} --ipadd {$params->ip} --save";
        }
        return self::$ssh->exec($commands);
    }

    public static function vdelip($params){
        if(isset($params->ips) && count($params->ips) > 1) {
            $commands = "";
            foreach($params->ips as $ip){
                $commands .= "/usr/sbin/vzctl set {$params->ctid} --ipdel {$ip} --save";
            }
        } else {
            $commands = "/usr/sbin/vzctl set {$params->ctid} --ipdel {$params->ip} --save";
        }
        return self::$ssh->exec($commands);
    }

    public static function vstart($ctid){
        return self::$ssh->exec("/usr/sbin/vzctl start {$ctid}");
    }

    public static function vstop($ctid){
        return self::$ssh->exec("/usr/sbin/vzctl stop {$ctid}");
    }

    public static function vrestart($ctid){
        return self::$ssh->exec("/usr/sbin/vzctl restart {$ctid}");
    }

    public static function vsuspend($ctid){
        return self::$ssh->exec("/usr/sbin/vzctl set {$ctid} --disabled yes --save; /usr/sbin/vzctl stop {$ctid}");
    }

    public static function vunsuspend($ctid){
        return self::$ssh->exec("/usr/sbin/vzctl set {$ctid} --disabled no --save; /usr/sbin/vzctl start {$ctid}");
    }

    public static function vset_password($ctid,$password){
        return self::$ssh->exec("/usr/sbin/vzctl start {$ctid}; /usr/sbin/vzctl set {$ctid} --userpasswd root:{$password} --save");
    }

    public static function vset_hostname($ctid,$hostname){
        return self::$ssh->exec("/usr/sbin/vzctl start {$ctid}; /usr/sbin/vzctl set {$ctid} --hostname {$hostname} --save");
    }

    public static function vset_dns($ctid,$dns1,$dns2){
        return self::$ssh->exec("/usr/sbin/vzctl set $ctid --nameserver {$dns1}  --nameserver {$dns2} --save;");
    }

    public static function venable_tuntap($ctid){
        $commands = "modprobe tun; /usr/sbin/vzctl set {$ctid} --devnodes net/tun:rw --save
                /usr/sbin/vzctl set {$ctid} --devices c:10:200:rw --save
                /usr/sbin/vzctl stop {$ctid}
                /usr/sbin/vzctl set {$ctid} --capability net_admin:on --save
                /usr/sbin/vzctl start {$ctid}
                /usr/sbin/vzctl exec {$ctid} mkdir -p /dev/net
                /usr/sbin/vzctl exec {$ctid} mknod /dev/net/tun c 10 200";
        return self::$ssh->exec($commands);
    }

    public static function vdisable_tuntap($ctid){
        $commands = "/usr/sbin/vzctl set {$ctid} --devnodes net/tun:none --save
                /usr/sbin/vzctl set {$ctid} --devices c:10:200:none --save
                /usr/sbin/vzctl stop {$ctid}
                /usr/sbin/vzctl set {$ctid} --capability net_admin:off --save
                /usr/sbin/vzctl start {$ctid}";
        return self::$ssh->exec($commands);
    }

    public static function venable_ppp($ctid){
        $commands = "/usr/sbin/vzctl stop {$ctid}
                /usr/sbin/vzctl set {$ctid} --features ppp:on --save
                /usr/sbin/vzctl start {$ctid}";
        return self::$ssh->exec($commands);
    }

    public static function vdisable_ppp($ctid){
        $commands = "/usr/sbin/vzctl set {$ctid} --features ppp:off --save;
                /usr/sbin/vzctl stop {$ctid};
                /usr/sbin/vzctl start {$ctid};
                ";
        return self::$ssh->exec($commands);
    }

    public static function vtuntap_status($ctid){
        if(strlen(self::$ssh->exec("/usr/sbin/vzctl exec {$ctid} cat /dev/net/tun")) == 48){
            return true;
        }
        return false;
    }

    public static function vppp_status($ctid){
        if(strlen(self::$ssh->exec("/usr/sbin/vzctl exec {$ctid} cat /dev/ppp")) == 41){
            return true;
        }
        return false;
    }

    public static function vstatus($ctid){
        return self::$ssh->exec("/usr/sbin/vzlist {$ctid} -Ho status");
    }
}