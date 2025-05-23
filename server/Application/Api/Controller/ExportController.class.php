<?php

namespace Api\Controller;

use Think\Controller;

class ExportController extends BaseController
{

    //导出整个项目为word
    public function word()
    {
        set_time_limit(100);
        ini_set('memory_limit', '800M');
        import("Vendor.Parsedown.Parsedown");
        $Parsedown = new \Parsedown();
        $convert = new \Api\Helper\Convert();
        $item_id =  I("item_id/d");
        $cat_id =  I("cat_id/d");
        $page_id =  I("page_id/d");
        $login_user = $this->checkLogin();
        if (!$this->checkItemEdit($login_user['uid'], $item_id)) {
            $this->message(L('no_permissions'));
            return;
        }

        // 获取项目信息
        $item = D("Item")->where("item_id = '$item_id' ")->find();
        
        // 检查是否为runapi项目并获取全局header
        $global_headers = array();
        if ($item['item_type'] == '3') { // runapi项目类型为3
            $runapiModel = new \Api\Model\RunapiModel();
            $globalParam = $runapiModel->getGlobalParam($item_id);
            if (isset($globalParam['header']) && !empty($globalParam['header'])) {
                $global_headers = $globalParam['header'];
            }
        }

        // 如果改用户是项目成员，且只分配了单个目录权限
        $tmpRes = D("ItemMember")->where(" item_id = '$item_id' and uid = '$login_user[uid]' and cat_id > 0 ")->find();
        if ($tmpRes) {
            $cat_id = $tmpRes['cat_id'];
        }
        // 如果改用户是团队成员，且只分配了该项目的单个目录权限
        $tmpRes = D("TeamItemMember")->where(" item_id = '$item_id' and member_uid = '$login_user[uid]' and cat_id > 0 ")->find();
        if ($tmpRes) {
            $cat_id = $tmpRes['cat_id'];
        }

        $menu = D("Item")->getContent($item_id, "*", "*", 1);
        if ($page_id > 0) {
            $pages[] = D("Page")->where(" page_id = '$page_id' ")->find();
        } else if ($cat_id) {
            foreach ($menu['catalogs'] as $key => $value) {
                if ($cat_id == $value['cat_id']) {
                    $pages = $value['pages'];
                    $catalogs = $value['catalogs'];
                } else {
                    if ($value['catalogs']) {
                        foreach ($value['catalogs'] as $key2 => $value2) {
                            if ($cat_id == $value2['cat_id']) {
                                $pages = $value2['pages'];
                                $catalogs = $value2['catalogs'];
                            } else {
                                if ($value2['catalogs']) {
                                    foreach ($value2['catalogs'] as $key3 => $value3) {
                                        if ($cat_id == $value3['cat_id']) {
                                            $pages = $value3['pages'];
                                            $catalogs = $value3['catalogs'];
                                        } else {
                                            if ($value3['catalogs']) {
                                                foreach ($value3['catalogs'] as $key4 => $value4) {
                                                    if ($cat_id == $value4['cat_id']) {
                                                        $pages = $value4['pages'];
                                                        $catalogs = $value4['catalogs'];
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } else {
            $pages = $menu['pages'];
            $catalogs = $menu['catalogs'];
        }

        $data = '';
        $parent = 1;

        // 如果是runapi项目且有全局header，则先添加全局header信息
        if (!empty($global_headers)) {
            $data .= "<h1>全局Header参数</h1>";
            $data .= '<div style="margin-left:20px;">';
            $data .= "<table>";
            $data .= "<thead><tr><th>参数名</th><th>值</th><th>是否启用</th><th>备注</th></tr></thead>";
            $data .= "<tbody>";
            foreach ($global_headers as $header) {
                $enabled = isset($header['enabled']) && $header['enabled'] ? '是' : '否';
                $name = isset($header['name']) ? $header['name'] : '';
                $value = isset($header['value']) ? $header['value'] : '';
                $remark = isset($header['remark']) ? $header['remark'] : '';
                $data .= "<tr><td>{$name}</td><td>{$value}</td><td>{$enabled}</td><td>{$remark}</td></tr>";
            }
            $data .= "</tbody></table>";
            $data .= '</div>';
            $parent++;
        }

        if ($pages) {
            foreach ($pages as $key => $value) {
                if (count($pages) > 1) {
                    $data .= "<h1>{$parent}、{$value['page_title']}</h1>";
                } else {
                    $data .= "<h1>{$value['page_title']}</h1>";
                }
                $data .= '<div style="margin-left:20px;">';
                $tmp_content = $convert->runapiToMd($value['page_content']);
                $value['page_content'] = $tmp_content ? $tmp_content : $value['page_content'];
                $data .= htmlspecialchars_decode($Parsedown->text($value['page_content']));
                $data .= '</div>';
                $parent++;
            }
        }
        //var_export($catalogs);
        if ($catalogs) {
            foreach ($catalogs as $key => $value) {
                $data .= "<h1>{$parent}、{$value['cat_name']}</h1>";
                $data .= '<div style="margin-left:0px;">';
                $child = 1;
                if ($value['pages']) {
                    foreach ($value['pages'] as $page) {
                        $data .= "<h2>{$parent}.{$child}、{$page['page_title']}</h2>";
                        $data .= '<div style="margin-left:0px;">';
                        $tmp_content = $convert->runapiToMd($page['page_content']);
                        $page['page_content'] = $tmp_content ? $tmp_content : $page['page_content'];
                        $data .= htmlspecialchars_decode($Parsedown->text($page['page_content']));
                        $data .= '</div>';
                        $child++;
                    }
                }
                if ($value['catalogs']) {
                    $parent2 = 1;
                    foreach ($value['catalogs'] as $key3 => $value3) {
                        $data .= "<h2>{$parent}.{$parent2}、{$value3['cat_name']}</h2>";
                        $data .= '<div style="margin-left:20px;">';
                        $child2 = 1;
                        if ($value3['pages']) {
                            foreach ($value3['pages'] as $page3) {
                                $data .= "<h3>{$parent}.{$parent2}.{$child2}、{$page3['page_title']}</h3>";
                                $data .= '<div style="margin-left:0px;">';
                                $tmp_content = $convert->runapiToMd($page3['page_content']);
                                $page3['page_content'] = $tmp_content ? $tmp_content : $page3['page_content'];
                                $data .= htmlspecialchars_decode($Parsedown->text($page3['page_content']));
                                $data .= '</div>';
                                $child2++;
                            }
                        }

                        if ($value3['catalogs']) {
                            $parent3 = 1;
                            foreach ($value3['catalogs'] as $key4 => $value4) {
                                $data .= "<h2>{$parent}.{$parent2}.{$parent3}、{$value4['cat_name']}</h2>";
                                $data .= '<div style="margin-left:0px;">';
                                $child3 = 1;
                                if ($value4['pages']) {
                                    foreach ($value4['pages'] as $page4) {
                                        $data .= "<h3>{$parent}.{$parent2}.{$parent3}.{$child3}、{$page4['page_title']}</h3>";
                                        $data .= '<div style="margin-left:30px;">';
                                        $tmp_content = $convert->runapiToMd($page4['page_content']);
                                        $page4['page_content'] = $tmp_content ? $tmp_content : $page4['page_content'];
                                        $data .= htmlspecialchars_decode($Parsedown->text($page4['page_content']));
                                        $data .= '</div>';
                                        $child3++;
                                    }
                                }
                                if ($value4['catalogs']) {
                                    $parent4 = 1;
                                    foreach ($value4['catalogs'] as $key5 => $value5) {
                                        $data .= "<h2>{$parent}.{$parent2}.{$parent3}.{$parent4}、{$value5['cat_name']}</h2>";
                                        $data .= '<div style="margin-left:0px;">';
                                        $child4 = 1;
                                        if ($value4['pages']) {
                                            foreach ($value4['pages'] as $page5) {
                                                $data .= "<h3>{$parent}.{$parent2}.{$parent3}.{$parent4}.{$child4}、{$page5['page_title']}</h3>";
                                                $data .= '<div style="margin-left:30px;">';
                                                $tmp_content = $convert->runapiToMd($page5['page_content']);
                                                $page5['page_content'] = $tmp_content ? $tmp_content : $page5['page_content'];
                                                $data .= htmlspecialchars_decode($Parsedown->text($page5['page_content']));
                                                $data .= '</div>';
                                                $child3++;
                                            }
                                        }
                                        $data .= '</div>';
                                        $parent3++;
                                    }
                                }
                                $data .= '</div>';
                                $parent3++;
                            }
                        }
                        $data .= '</div>';
                        $parent2++;
                    }
                }
                $data .= '</div>';
                $parent++;
            }
        }

        output_word($data, $item['item_name']);
    }

    //导出整个项目为markdown压缩包
    public function markdown()
    {
        set_time_limit(100);
        ini_set('memory_limit', '800M');
        $item_id =  I("item_id/d");
        $login_user = $this->checkLogin();
        if (!$this->checkItemEdit($login_user['uid'], $item_id)) {
            $this->message(L('no_permissions'));
            return;
        }

        $item = D("Item")->where("item_id = '$item_id' ")->find();

        $exportJson = D("Item")->export($item_id, true);
        $exportData = json_decode($exportJson, 1);
        $zipArc = new \ZipArchive();
        $temp_file = tempnam(sys_get_temp_dir(), 'Tux') . "_showdoc_.zip";
        $temp_dir = sys_get_temp_dir() . "/showdoc_" . time() . rand();
        mkdir($temp_dir);
        unset($exportData['members']);
        file_put_contents($temp_dir . '/' . 'info.json', json_encode($exportData));
        file_put_contents($temp_dir . '/' . 'readme.md', "由于页面标题可能含有特殊字符导致异常，所以markdown文件的命名均为英文（md5串），以下是页面标题和文件的对应关系：" . PHP_EOL . PHP_EOL);

        $exportData['pages'] = $this->_markdownTofile($exportData['pages'], $temp_dir);
        $ret = $this->_zip($temp_dir, $temp_file);

        clear_runtime($temp_dir);
        rmdir($temp_dir);
        header("Cache-Control: max-age=0");
        header("Content-Description: File Transfer");
        header('Content-disposition: attachment; filename=showdoc.zip'); // 文件名
        header("Content-Type: application/zip"); // zip格式的
        header("Content-Transfer-Encoding: binary"); // 告诉浏览器，这是二进制文件
        header('Content-Length: ' . filesize($temp_file)); // 告诉浏览器，文件大小
        @readfile($temp_file); //输出文件;
        unlink($temp_file);
    }

    public function checkMarkdownLimit()
    {
        $login_user = $this->checkLogin();
        $export_format =  I("export_format");
        $this->sendResult(array());
    }

    private function _markdownTofile($catalogData,  $temp_dir)
    {
        if ($catalogData['pages']) {
            foreach ($catalogData['pages'] as $key => $value) {
                $t = rand(1000, 100000);
                //把页面内容保存为md文件
                $filename = md5($value['page_title'] . '_' . $t) . ".md";
                file_put_contents($temp_dir . '/' . $filename, htmlspecialchars_decode($value['page_content']));

                file_put_contents($temp_dir . '/' . 'readme.md', $value['page_title'] . " —— prefix_" .  $filename  . PHP_EOL, FILE_APPEND);
            }
        }

        if ($catalogData['catalogs']) {
            foreach ($catalogData['catalogs'] as $key => $value) {
                $catalogData['catalogs'][$key] = $this->_markdownTofile($value,  $temp_dir);
            }
        }
        return $catalogData;
    }

    private function _zip($temp_dir, $temp_file)
    {
        $zipArc = new \ZipArchive();
        if (!$zipArc->open($temp_file, \ZipArchive::CREATE)) {
            return FALSE;
        }
        $dir = opendir($temp_dir);
        while (false != ($file = readdir($dir))) {
            if (($file != ".") and ($file != "..")) {
                $res = $zipArc->addFromString("prefix_" . $file, file_get_contents($temp_dir . "/" . $file));
            }
        }
        closedir($dir);

        if (!$res) {
            $zipArc->close();
            return FALSE;
        }
        return $zipArc->close();
    }
}
