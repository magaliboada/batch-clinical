<?php
namespace App\Command;

use Cake\Core\Configure;
use Cake\I18n\Time;

class BatchBuilder
{

    public function cleanField($string)
    {
        $string = str_replace("\n", " ", $string);
        $string = str_replace(";", " ", $string);

        return $string;
    }

    public function customFormatDate($date)
    {
        $date = new Time($date);
        $date = $date->i18nFormat('ddMMyyyy');

        return $date;
    }

    public function moveKeyBefore($arr, $find, $move)
    {
        if (!isset($arr[$find], $arr[$move])) {
            return $arr;
        }

        $elem = [$move=>$arr[$move]];  // cache the element to be moved
        $start = array_splice($arr, 0, array_search($find, array_keys($arr)));
        unset($start[$move]);  // only important if $move is in $start

        return $start + $elem + $arr;
    }

    public function arrayToCSV($header, $array, $type, $version)
    {
        $array = json_decode(json_encode($array), true);

        if (count($array) > 0) {
            Configure::load('config_vars');
            $realPath = Configure::read('paths.export');
            $folderName = Configure::read('paths.folder');

            if (!file_exists($realPath.'/'.$folderName)) {
                mkdir($realPath.'/'.$folderName, 0755, true);
            }

            $currentPath = realpath($realPath.'/'.$folderName);
            $csv = "";

            /* CAPÇALERA

             foreach ($header as $item) {
                 $csv .= $item.";";
             }
             $csv .= "\n";

             */

            foreach ($array as $item) {
                foreach ($item as $value) {
                    if (is_array($value)) {
                        $value = current($value);
                    }
                    $value = $this->cleanField($value);
                    $csv .= (String)$value.";";
                }
                $csv .= "\n";
            }

            $csv = mb_convert_encoding($csv, 'ISO-8859-1', 'UTF-8');
            $csv_filename =  $type.date("Y-m-d")."_".$version.".csv";
            $csv_handler = fopen($currentPath.'/'.$csv_filename, 'w');

            fwrite($csv_handler, $csv);
            fclose($csv_handler);
        }
    }
}
