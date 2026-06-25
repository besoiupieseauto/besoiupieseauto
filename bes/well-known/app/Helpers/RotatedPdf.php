<?php

namespace App\Helpers;

use Codedge\Fpdf\Fpdf\Fpdf;

class RotatedPdf extends Fpdf
{
    protected $angle = 0;

    /**
     * PDF generation helper for Rotate
     */
    public function Rotate($angle, $x = -1, $y = -1) {
        if ($x == -1) {
            $x = $this->x;
        }
        
        if ($y == -1) {
            $y = $this->y;
        }

        if ($this->angle != 0) {
            $this->_out('Q');
        }

        $this->angle = $angle;

        if ($angle != 0) {
            $angle *= M_PI / 180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf(
                            'q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm',
                            $c,
                            $s,
                            -$s,
                            $c,
                            $cx,
                            $cy,
                            -$cx,
                            -$cy
            ));
        }
    }


    public function transforma($nr) {
        $bnr = explode(".", $nr);
        $cstr = "";
        $zecimal = count($bnr);
        for ($i = 0; $i < $zecimal; $i++) {
            $nr = str_replace(array(",", "."), "", $bnr[$i]);
            $sute = "";
            $zeci = "";
            $uni = "";
            $un = array("miliard", "miliarde", "milion", "milioane", "mie", "mii", "", "","");
            $sir = "";
            $s = "1";
            $num = 4;
            while ($s != "") {
                $s = substr($nr, -3);
                if ($s == "")
                    break;
                $nr = (strlen($nr) - 3 > 0) ? substr($nr, 0, strlen($nr) - 3) : "";
                $ss = "";
                if ($s != "") {
                    switch (strlen($s)) {
                        case 3:
                            $sute = substr($s, 0, 1);
                            $zeci = substr($s, 1, 1);
                            $uni = substr($s, 2, 1);
                            $ss = $this->numar($sute, 1) . (($sute == 1) ? "suta" : (($sute > 1) ? "sute" : ""));
                            if ($zeci == 1 && $uni != 0)
                                $ss = $ss . $this->numar($uni, 4) . "sprezece" . $un[$num * 2];
                            else if ($zeci == 1 && $uni == 0)
                                $ss = "zece" . $un[$num * 2];
                            else {
                                $ss = $ss . $this->numar($zeci, 2) . (($zeci != 0) ? "zeci" : "") . (($uni != "0") ? "si" . $this->numar($uni, 3) : "") . (($s != "000") ? $un[$num * 2 - 1] : "");
                            }
                            $sir = $ss . $sir;
                            break;
                        case 2:
                            $sute = 0;
                            $zeci = substr($s, 0, 1);
                            $uni = substr($s, 1, 1);
                            if ($zeci == 1 && $uni != 0)
                                $ss = $ss . $this->numar($uni, 4) . "sprezece" . $un[$num * 2 - 1];
                            else if ($zeci == 1 && $uni == 0)
                                $ss = "zece" . $un[$num * 2];
                            else
                                $ss = $ss . $this->numar($zeci, 2) . (($zeci != 0) ? "zeci" : "") . (($uni != "0") ? "si" . $this->numar($uni, 3) : "") . $un[$num * 2 - 1];
                            $sir = $ss . $sir;
                            break;
                        case 1:
                            $sute = 0;
                            $zeci = 0;
                            $uni = substr($s, 0, 1);
                            $sir = $this->numar($uni, ($num == 3) ? 1 : 3) . (($uni == 1) ? $un[($num - 1) * 2] : $un[($num - 1) * 2 + 1]) . $sir;
                            break;
                    }
                    $num--;
                }
            }
            if ($i == 0)
                if ($zecimal == 1)
                    $cstr .= $sir . "lei";
                else
                    $cstr .= $sir . "lei si";
            else if ($i == 1)
                $cstr .= $sir . "bani";
        }
        return $cstr;
    }


    private function numar($s, $z) {
		$numar="";
        switch ($z) {
            case 1:
                switch ($s) {
                    case "-": $numar = "minus";
                        break;
                    case 0: $numar = "zero";
                        break;
                    case 1: $numar = "o";
                        break;
                    case 2: $numar = "doua";
                        break;
                    case 3: $numar = "trei";
                        break;
                    case 4: $numar = "patru";
                        break;
                    case 5: $numar = "cinci";
                        break;
                    case 6: $numar = "sase";
                        break;
                    case 7: $numar = "sapte";
                        break;
                    case 8: $numar = "opt";
                        break;
                    case 9: $numar = "noua";
                }
                break;
            case 2:
                switch ($s) {
                    case 0: $numar = "zero";
                        break;
                    case 1: $numar = "zece";
                        break;
                    case 2: $numar = "doua";
                        break;
                    case 3: $numar = "trei";
                        break;
                    case 4: $numar = "patru";
                        break;
                    case 5: $numar = "cinci";
                        break;
                    case 6: $numar = "sase";
                        break;
                    case 7: $numar = "sapte";
                        break;
                    case 8: $numar = "opt";
                        break;
                    case 9: $numar = "noua";
                }
                break;
            case 3:
                switch ($s) {
                    case 0: $numar = "zero";
                        break;
                    case 1: $numar = "unu";
                        break;
                    case 2: $numar = "doi";
                        break;
                    case 3: $numar = "trei";
                        break;
                    case 4: $numar = "patru";
                        break;
                    case 5: $numar = "cinci";
                        break;
                    case 6: $numar = "sase";
                        break;
                    case 7: $numar = "sapte";
                        break;
                    case 8: $numar = "opt";
                        break;
                    case 9: $numar = "noua";
                }
                break;
            case 4:
                switch ($s) {
                    case 0: $numar = "zero";
                        break;
                    case 1: $numar = "un";
                        break;
                    case 2: $numar = "doi";
                        break;
                    case 3: $numar = "trei";
                        break;
                    case 4: $numar = "patru";
                        break;
                    case 5: $numar = "cinci";
                        break;
                    case 6: $numar = "sase";
                        break;
                    case 7: $numar = "sapte";
                        break;
                    case 8: $numar = "opt";
                        break;
                    case 9: $numar = "noua";
                }
                break;
        }
        return $numar;
    }


    public function _endpage()
    {
        if ($this->angle != 0) {
            $this->angle = 0;
            $this->_out('Q');
        }
        parent::_endpage();
    }
}
