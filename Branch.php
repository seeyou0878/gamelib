<?php

namespace Lib;

class Branch
{
    private function priority($type, $host, $status_id, $arr_super)
    {
        
        $result = 0;
        $super = isset($arr_super[$host]);
        
        if ($status_id != 1 && !$super) {
            $arr = [
                'admin' => 3,
                'alias' => 2,
                'front' => 1,
            ];
            $result = $arr[$type] ?? 0;
        } elseif ($status_id != 1 && $super) {
            $arr = [
                'admin' => 6,
                'alias' => 5,
                'front' => 4,
            ];
            $result = $arr[$type] ?? 0;
        } elseif ($status_id == 1) {
            $arr = [
                'admin' => 9,
                'alias' => 8,
                'front' => 7,
            ];
            $result = $arr[$type] ?? 0;
        }
        
        return $result;
    }

    public function branch($host = '')
    {
        // \Box::obj('db', \Config('xct.medoo'));
        // $branch = \Box::obj('db')->select('t_branch', '*');
        $branch = \DB::table('t_branch')->get();
        $branch = json_decode(json_encode($branch), true);
    
        $host = $host?: ($_SERVER['HTTP_HOST'] ?? '');

        $result = array(
            'host' => $host,
            'branch_id' => 0,
            'is_front' => 0, // front site
            'is_admin' => 0, // admin site
            'is_close' => 0, // under maintenance
            'agent' => '',
        );

        $arr_match = array_fill(1, 9, '');

        foreach ($branch as $k => $v) {
            // init
            $v = array_map('trim', $v);
        
            $arr_super = [];
            // super
            foreach (preg_split('/[\r\n\s]+/', $v['super_alias']) as $domain) {
                $arr_super[$domain] = '';
            }
        
            // admin
            foreach (preg_split('/[\r\n\s]+/', $v['admin_alias']) as $domain) {
                if ($domain == $host) {
                    $priority = $this->priority('admin', $host, $v['status_id'], $arr_super);

                    $arr_match[$priority] = [
                        'host' => $host,
                        'branch_id' => $v['id'],
                        'is_front' => 0, // front site
                        'is_admin' => 1, // admin site
                        'is_close' => isset($arr_super[$host])? 0: ($v['status_id'] != 1), // under maintenance
                        'agent' => '',
                    ];
                }
            }
        
            // front
            foreach (preg_split('/[\r\n\s]+/', $v['domain']) as $domain) {
                $sub = '.' . $domain;
                if ($host == substr_replace($host, $sub, -strlen($sub)) || $host == $domain) {
                    $priority = $this->priority('front', $host, $v['status_id'], $arr_super);
                    
                    $arr_match[$priority] = [
                        'host' => $host,
                        'branch_id' => $v['id'],
                        'is_front' => 1, // front site
                        'is_admin' => 0, // admin site
                        'is_close' => isset($arr_super[$host])? 0: ($v['status_id'] != 1), // under maintenance
                        'agent' => str_replace($sub, '', $host),
                    ];
                }
            }
            
            //alias
            foreach (preg_split('/[\r\n\s]+/', $v['front_alias']) as $pair) {
                $arr = preg_split('/[\s,]+/', $pair);
                if (($arr[1] ?? '') == $host) {
                    $priority = $this->priority('alias', $host, $v['status_id'], $arr_super);
                    
                    $arr_match[$priority] = [
                        'host' => $host,
                        'branch_id' => $v['id'],
                        'is_front' => 1, // front site
                        'is_admin' => 0, // admin site
                        'is_close' => isset($arr_super[$host])? 0: ($v['status_id'] != 1), // under maintenance
                        'agent' => $arr[0],
                    ];
                }
            }
        }
        foreach ($arr_match ?? [] as $v) {
            $result = $v ?: $result;
        }

        return $result;
    }
}
