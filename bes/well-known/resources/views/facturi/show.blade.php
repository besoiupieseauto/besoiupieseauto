<?php

session_start();

if (!isset($_SESSION['user_login_status']) AND $_SESSION['user_login_status'] != 1) {

    header("location: login.php");

    exit;

}

require_once ("config/activ.php");

$facturi_active = "active";

$title = "Editare Factura | Facturi";



/* Connect To Database */

require_once ("config/db.php"); //

require_once ("config/conexion.php"); //

$tip_cmd=3;

//generez factura din comanda

if (isset($_GET['id_comanda'])) {

    $id_comanda = $_GET['id_comanda'];

    $tip_cmd = intval($_GET['tip_comanda']);

    if ($tip_cmd === 0) {

        $tabel1 = "comenzi";

        $tabel2 = "detaliu";

        $id_incasare = 1;

    }else { 

    if ($tip_cmd === 2) {

        $tabel1 = "facturi";

        $tabel2 = "facturidetails";

        $id_incasare = 1;

    } 

    else {

        $tabel1 = "comenzi_ext";

        $tabel2 = "detaliu_ext";

        $id_incasare = 3;

    }

    }

    if ($tip_cmd === 2) {

    $sintaxa = "select t1.CustomerID as idclient,t2.Quantity as cantitate,t2.UnitPrice as pret,t2.ProductId as idprodus,t3.TVA,t3.um "

            . "from " . $tabel1 . " t1 "

            . "inner join " . $tabel2 . " t2 on t1.OrderID = t2.OrderID "

            . "inner join produse t3 on t2.ProductId =t3.idprodus "

            . "where t1.OrderID ='" . $id_comanda . "'";

    }

    else {

       $sintaxa = "select t1.idclient,t2.cantitate,t2.pret,t2.idprodus,t3.TVA,t3.um "

            . "from " . $tabel1 . " t1 "

            . "inner join " . $tabel2 . " t2 on t1.idcomanda = t2.idcomanda "

            . "inner join produse t3 on t2.idprodus =t3.idprodus "

            . "where t1.idcomanda ='" . $id_comanda . "'";  

    }

  

    $sql1 = mysqli_query($con, $sintaxa);

    $rw = mysqli_fetch_array($sql1);

    $id_client = $rw['idclient'];

    

    //inserez antet factura

    $sql_nr = mysqli_query($con, "select max(OrderID) as last from facturi order by OrderID desc limit 0,1 ");

    $rw_nr = mysqli_fetch_array($sql_nr);

    $numar_factura = $rw_nr['last'] + 1;

	//id_factura

	$sql_idff = mysqli_query($con, "select max(id_fact) as lastff from facturi order by OrderID desc limit 0,1 ");

	$rw_ff = mysqli_fetch_array($sql_idff);

	$numar_ff = $rw_ff['lastff'] + 1;

    $date = date("d/m/Y");

    $data_noua = date("Y-m-d H:i:s", strtotime(str_replace('/', '-', $date)));

    $data_scadenta = $data_noua;

    $sqle = "INSERT INTO facturi (OrderId,CustomerID,EmployeeID,OrderDate,RequiredDate,seria,valid,tip_incas,id_chitanta,id_comanda,tip_comanda,id_fact) VALUES "

            . "('$numar_factura','$id_client','2','$data_noua','$data_scadenta','BPA_C','1','$id_incasare','0','$id_comanda','$tip_cmd','$numar_ff')";

			

    $insert = mysqli_query($con, $sqle);

    //actualizez comanda cu nr. factura

	if($tabel1=="facturi"){

    $sqlup = "UPDATE " . $tabel1 . " SET id_fact='" . $numar_factura . "' where id_comanda='" . $id_comanda . "'";

	}else{

	$sqlup = "UPDATE " . $tabel1 . " SET id_factura='" . $numar_factura . "' where idcomanda='" . $id_comanda . "'";

	}

    $upda = mysqli_query($con, $sqlup);

    //inseram produsele in factura

    $sql = mysqli_query($con, $sintaxa);

    while ($row = mysqli_fetch_array($sql)) {

        $id_produs = $row["idprodus"];

        $cantitate = $row['cantitate'];

        $cantitate_f = number_format($cantitate, 2);

        $pret_cutva = $row['pret'];

        $tva = $row['TVA'];

        $um = $row['um'];

        $pret_unitar = $pret_cutva / (($tva + 100) / 100);

            if ($tip_cmd === 2) {

               $cantitate = $cantitate*(-1); 

               $cantitate_f = number_format($cantitate, 2);

               $pret_unitar = $pret_cutva;

            }

        $pret_unitar_f = number_format($pret_unitar, 2);

        $pret_unitar_r = str_replace(",", "", $pret_unitar_f);

        $valoare = $pret_unitar * $cantitate; //Valoare

        $valoare_f = number_format($valoare, 2);

        $valoare_r = str_replace(",", "", $valoare_f);

        $ctva = $valoare * $tva / 100;

        $ctva_f = number_format($ctva, 2);

        $ctva_r = str_replace(",", "", $ctva_f);

        $pret_cu_tva = $valoare + $ctva;

        $pret_cu_tva_f = number_format($pret_cu_tva, 2);

        $pret_cu_tva_r = str_replace(",", "", $pret_cu_tva_f);

        $insert_detail = mysqli_query($con, "INSERT INTO facturidetails (OrderID,ProductID,UnitPrice,Quantity,tva,total) VALUES "

                . "('$numar_factura','$id_produs','$pret_unitar_r','$cantitate_f','$ctva_r','$pret_cu_tva_r')");

    }

}

//aduc datele

if (isset($_GET['id_factura'])) {

    $id_factura = intval($_GET['id_factura']);

} else {

    if (isset($_GET['id_comanda'])) {

        $id_factura = $numar_factura;

    } else {

        header("location: facturi.php");

        exit;

    }

}

$campuri = "*";

$sqle = "select $campuri from facturi, clienti where facturi.customerID=clienti.idclienti and facturi.OrderID='" . $id_factura . "'";

$sql_factura = mysqli_query($con, $sqle);

$count = mysqli_num_rows($sql_factura);

if ($count == 1) {

    $rw_factura = mysqli_fetch_array($sql_factura);

    $id_client = $rw_factura['idclienti'];

    $nume_client = $rw_factura['companie'] . $rw_factura['nume'];

    $telefon_client = $rw_factura['telefon'];

    $cui_client = $rw_factura['cif'];

    $id_agent = $rw_factura['EmployeeID'];

    $data_factura = date("d/m/Y", strtotime($rw_factura['OrderDate']));

    $datascadenta = date("d/m/Y", strtotime($rw_factura['RequiredDate']));

    $numar_factura = $rw_factura['OrderID'];

    $id_factura = $rw_factura['OrderID'];

    $id_incas = $rw_factura['tip_incas'];

    $_SESSION['id_factura'] = $id_factura;

} else {

    header("location: facturi.php");

    exit;

}

?>

<!DOCTYPE html>

<html lang="en">

    <head>

        <?php include("head.php"); ?>

    </head>

    <body>

        <?php

        include("navbar.php");

        ?>  

        <div class="jumbotron">        

            <div class="container-fluid">

                <div class="panel panel-info">

                    <div class="panel-heading">

                        <h4><i class='glyphicon glyphicon-edit'></i> Editare Factura</h4>

                    </div>

                    <div class="panel-body">

                        <?php

                        include("modal/cauta_produs.php");

                        include("modal/client_nou.php");

                        include("modal/produs_nou.php");

                        ?>

                        <form class="form-horizontal" role="form" id="date_factura">

                           <input id="tip_cmd" name="tip_cmd" type='hidden' value="<?php echo $tip_cmd; ?>">

						   <input id="tip_comanda" name="tip_comanda" type='hidden' value="<?php echo $tip_cmd; ?>">

                            <div class="form-group row">

                                <label for="nume_client" class="col-md-1 control-label">Client</label>

                                <div class="col-md-3">

                                    <div class="input-group">

                                        <input type="text" class="form-control input-sm" id="nume_client" value="<?php echo $nume_client; ?>" required>

                                        <input id="id_client" name="id_client" type='hidden' value="<?php echo $id_client; ?>">

                                        <span class="input-group-btn">

                                            <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#client_nou">

                                                <span class="glyphicon glyphicon-plus"></span>

                                            </button>

                                        </span>

                                    </div>

                                </div>  

                                <label for="tel1" class="col-md-1 control-label">Telefon</label>

                                <div class="col-md-2">

                                    <input type="text" class="form-control input-sm" id="tel1" name="tel1" placeholder="Telefon" value="<?php echo $telefon_client; ?>" readonly>

                                </div>

                                <label for="mail" class="col-md-1 control-label">CUI</label>

                                <div class="col-md-3">

                                    <input type="text" class="form-control input-sm" id="cui_client" name="cui_client" placeholder="CUI" readonly value="<?php echo $cui_client; ?>">

                                </div>

                            </div>

                            <div class="form-group row">

                                <label for="agent" class="col-md-1 control-label">Agent</label>

                                <div class="col-md-3">

                                    <select class="form-control input-sm" id="vanzator_nou" name="vanzator_nou">

                                        <?php

                                        $sql_vendedor = mysqli_query($con, "select * from employees order by LastName");

                                        while ($rw = mysqli_fetch_array($sql_vendedor)) {

                                            $id_vanzator = $rw["EmployeeId"];

                                            $nume_vanzator = $rw["FirstName"] . " " . $rw["LastName"];

                                            if ($id_vanzator === $id_agent) {

                                                $selected = "selected";

                                            } else {

                                                $selected = "";

                                            }

                                            ?>

                                            <option <?php echo $selected; ?> value="<?php echo $id_vanzator ?>"><?php echo $nume_vanzator ?></option>

                                            <?php

                                        }

                                        ?>

                                    </select>

                                </div>

                                <label for="data" class="col-md-1 control-label">Data</label>

                                <div class="col-md-2">

                                    <input type="text" class="form-control input-sm" id="data" name="data" value="<?php echo $data_factura; ?>" readonly>

                                </div>



                                <label for="datascadenta" class="col-md-2 control-label">Data scadenta</label>

                                <div class="col-md-2">

                                    <input class="form-control" id="datascadenta" name="datascadenta" placeholder="DD/MM/YYYY" type="text" value="<?php echo $datascadenta ?>" readonly/>

                                </div>

                            </div>

                            <div class="form-group row">

                                <label for="id_incasare" class="col-md-1 control-label">Tip incasare</label>

                                <div class="col-md-3">

                                    <select class="form-control input-sm" id="id_incasare" name="id_incasare">

                                        <?php

                                        $sql_incasare = mysqli_query($con, "SELECT * FROM tip_plata order by id_plata");

                                        while ($rw = mysqli_fetch_array($sql_incasare)) {

                                            $id_incasare = $rw["id_plata"];

                                            $nume_incasare = $rw["denumire"];

                                            if ($id_incasare === $id_incas) {

                                                $selected = "selected";

                                            } else {

                                                $selected = "";

                                            }

                                            ?>

                                            <option value="<?php echo $id_incasare ?>" <?php echo $selected; ?>><?php echo $nume_incasare ?></option>

                                            <?php

                                        }

                                        ?>

                                    </select>

                                </div>

                            </div>



                            <div class="col-md-12">

                                <div class="pull-right">

                                    <button type="button" class="btn btn-default" data-toggle="modal" data-target="#myModal">

                                        <span class="glyphicon glyphicon-search"></span> Adauga produs

                                    </button>

                                    <button type="submit" class="btn btn-default">

                                        <?php if($tip_cmd<>3){ ?>

                                        <span class="glyphicon glyphicon-refresh"></span> Salveaza

                                        <?php } else{ ?>

                                        <span class="glyphicon glyphicon-refresh"></span> Actualizeaza

                                        <?php } ?>

                                    </button>                                

                                </div>	

                            </div>

                        </form>	

                        <div class="clearfix"></div>

                        <div class="editare_factura" class='col-md-12' style="margin-top:10px"></div><!-- Date ajax -->	



                        <div id="rezultat" class='col-md-12' style="margin-top:10px"></div><!-- Date ajax -->			



                    </div>

                </div>		



            </div>

            <?php

            include("footer.php");

            ?>

            <script type="text/javascript" src="js/VentanaCentrada.js"></script>

            <script type="text/javascript" src="js/editare_factura.js"></script>

            <script type="text/javascript" src="js/prod_nou.js"></script>

            <link rel="stylesheet" href="css/jquery-ui.css">

            <script type="text/javascript" src="js/jquery-ui.js"></script>   

            <script src="js/foundation-datepicker.js"></script>         

            <script src="js/foundation-datepicker.ro.js"></script>               

            <script>

                $(window).load(function () {



                    //$('#date').datepicker();

                    $('#datascadenta').fdatepicker({

                        format: 'dd/mm/yyyy',

                        language: 'ro'

                    });

                    //glDatePicker();

                });

                $(function () {

                    $("#nume_client").autocomplete({

                        source: "./ajax/autocomplete/clienti.php",

                        minLength: 2,

                        select: function (event, ui) {

                            event.preventDefault();

                            $('#id_client').val(ui.item.id_client);

                            $('#nume_client').val(ui.item.nume_client);

                            $('#tel1').val(ui.item.telefon_client);

                            $('#cui').val(ui.item.cui_client);





                        }

                    });





                });



                $("#nume_client").on("keydown", function (event) {

                    if (event.keyCode == $.ui.keyCode.LEFT || event.keyCode == $.ui.keyCode.RIGHT || event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE || event.keyCode == $.ui.keyCode.BACKSPACE)

                    {

                        $("#id_client").val("");

                        $("#tel1").val("");

                        $("#cui").val("");



                    }

                    if (event.keyCode == $.ui.keyCode.DELETE) {

                        $("#nume_client").val("");

                        $("#id_client").val("");

                        $("#tel1").val("");

                        $("#cui").val("");

                    }

                });

            </script>

        </div>

    </body>

</html>