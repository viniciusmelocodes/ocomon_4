<?php
/* Copyright 2020 Flávio Ribeiro

This file is part of OCOMON.

OCOMON is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

OCOMON is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Foobar; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */ session_start();

if (!isset($_SESSION['s_logado']) || $_SESSION['s_logado'] == 0) {
    $_SESSION['session_expired'] = 1;
    echo "<script>top.window.location = '../../index.php'</script>";
    exit;
}

require_once __DIR__ . "/" . "../../includes/include_geral_new.inc.php";
require_once __DIR__ . "/" . "../../includes/classes/ConnectPDO.php";

use includes\classes\ConnectPDO;

$conn = ConnectPDO::getInstance();

$auth = new AuthNew($_SESSION['s_logado'], $_SESSION['s_nivel'], 2, 1);

$_SESSION['s_page_ocomon'] = $_SERVER['PHP_SELF'];

$filter_areas = "";
$areas_names = "";
if (isAreasIsolated($conn) && $_SESSION['s_nivel'] != 1) {
    /* Visibilidade isolada entre áreas para usuários não admin */
    $u_areas = $_SESSION['s_uareas'];
    $filter_areas = " AND sis_id IN ({$u_areas}) ";

    $array_areas_names = getUserAreasNames($conn, $u_areas);

    foreach ($array_areas_names as $area_name) {
        if (strlen($areas_names))
            $areas_names .= ", ";
        $areas_names .= $area_name;
    }
}

$json = 0;
$json2 = 0;

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="../../includes/css/estilos.css" />
    <link rel="stylesheet" type="text/css" href="../../includes/components/jquery/datetimepicker/jquery.datetimepicker.css" />
    <link rel="stylesheet" type="text/css" href="../../includes/components/bootstrap/custom.css" />
    <link rel="stylesheet" type="text/css" href="../../includes/components/fontawesome/css/all.min.css" />

    <style>
        .chart-container {
            position: relative;
            /* height: 100%; */
            max-width: 100%;
            margin-left: 10px;
            margin-right: 10px;
            margin-bottom: 30px;
        }
    </style>

    <title>OcoMon&nbsp;<?= VERSAO; ?></title>
</head>

<body>
    
    <div class="container">
        <div id="idLoad" class="loading" style="display:none"></div>
    </div>


    <div class="container">
        <h5 class="my-4"><i class="fas fa-percentage text-secondary"></i>&nbsp;<?= TRANS('TICKETS_BY_STATUS'); ?></h5>
        <div class="modal" id="modal" tabindex="-1" style="z-index:9001!important">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div id="divDetails">
                    </div>
                </div>
            </div>
        </div>

        <?php
        if (isset($_SESSION['flash']) && !empty($_SESSION['flash'])) {
            echo $_SESSION['flash'];
            $_SESSION['flash'] = '';
        }


        if (!isset($_POST['action'])) {

        ?>
            <form method="post" action="<?= $_SERVER['PHP_SELF']; ?>" id="form">
                <div class="form-group row my-4">
                    <label for="area" class="col-md-2 col-form-label col-form-label-sm text-md-right"><?= TRANS('RESPONSIBLE_AREA'); ?></label>
                    <div class="form-group col-md-10">
                        <select class="form-control sel2" id="area" name="area">
                            <option value="-1"><?= TRANS('ALL'); ?></option>
                            <?php
                            $sql = "SELECT * FROM sistemas WHERE sis_atende = 1 {$filter_areas} AND sis_status NOT IN (0) ORDER BY sistema";
                            $resultado = $conn->query($sql);
                            foreach ($resultado->fetchAll() as $rowArea) {
                                print "<option value='" . $rowArea['sis_id'] . "'";
                                echo ($rowArea['sis_id'] == $_SESSION['s_area'] ? ' selected' : '');
                                print ">" . $rowArea['sistema'] . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <label for="d_ini" class="col-md-2 col-form-label col-form-label-sm text-md-right"><?= TRANS('START_DATE'); ?></label>
                    <div class="form-group col-md-10">
                        <input type="text" class="form-control " id="d_ini" name="d_ini" value="<?= date("01/m/Y"); ?>" autocomplete="off" required />
                    </div>

                    <label for="d_fim" class="col-md-2 col-form-label col-form-label-sm text-md-right"><?= TRANS('END_DATE'); ?></label>
                    <div class="form-group col-md-10">
                        <input type="text" class="form-control " id="d_fim" name="d_fim" value="<?= date("d/m/Y"); ?>" autocomplete="off" required />
                    </div>


                    <div class="row w-100"></div>
                    <div class="form-group col-md-8 d-none d-md-block">
                    </div>
                    <div class="form-group col-12 col-md-2 ">

                        <input type="hidden" name="action" value="search">
                        <button type="submit" id="idSubmit" name="submit" class="btn btn-primary btn-block"><?= TRANS('BT_SEARCH'); ?></button>
                    </div>
                    <div class="form-group col-12 col-md-2">
                        <button type="reset" class="btn btn-secondary btn-block" onClick="parent.history.back();"><?= TRANS('BT_CANCEL'); ?></button>
                    </div>
                    

                </div>
            </form>
            <?php
        } else {

            $hora_inicio = ' 00:00:00';
            $hora_fim = ' 23:59:59';

            $terms = "";
            $query = "SELECT status.status as status, count(o.status) AS quantidade, 
                        sistemas.sistema as area FROM status, ocorrencias o , sistemas 
                        WHERE status.stat_id = o.status AND status.stat_painel NOT IN (3) 
                            AND sistemas.sis_id = o.sistema 
                            ";
            
            $queryToChart = "SELECT status.status as status, count(o.status) AS quantidade 
            FROM status, ocorrencias o , sistemas 
            WHERE status.stat_id = o.status AND status.stat_painel NOT IN (3) 
                AND sistemas.sis_id = o.sistema 
                ";

            if (!empty($filter_areas)) {
                /* Nesse caso o usuário só pode filtrar por áreas que faça parte */
                if (!empty($_POST['area']) && ($_POST['area'] != -1)) {
                    $query .= " AND o.sistema = " . $_POST['area'] . "";
                    $queryToChart .= " AND o.sistema = " . $_POST['area'] . "";

                    $getAreaName = "SELECT * from sistemas where sis_id = " . $_POST['area'] . "";
                    $exec = $conn->query($getAreaName);
                    $rowAreaName = $exec->fetch();
                    $nomeArea = $rowAreaName['sistema'];
                    $terms .= TRANS('FILTERED_AREA') . ": {$nomeArea}";
                } else {
                    $query .= " AND o.sistema IN ({$u_areas}) ";
                    $queryToChart .= " AND o.sistema IN ({$u_areas}) ";
                    $terms .= TRANS('FILTERED_AREA') . ": [" . $areas_names . "]";
                }
            } else

            if (isset($_POST['area']) and ($_POST['area'] != -1) and (($_POST['area'] == $_SESSION['s_area']) || ($_SESSION['s_nivel'] == 1))) {
                $query .= " AND o.sistema = " . $_POST['area'] . "";
                $queryToChart .= " AND o.sistema = " . $_POST['area'] . "";
                $getAreaName = "SELECT * from sistemas where sis_id = " . $_POST['area'] . "";
                $exec = $conn->query($getAreaName);
                $rowAreaName = $exec->fetch();
                $nomeArea = $rowAreaName['sistema'];

                $terms .= TRANS('FILTERED_AREA') . ": {$nomeArea}";
            } else
            if ($_SESSION['s_nivel'] != 1) {
                $_SESSION['flash'] = message('info', '', TRANS('MSG_CONSULT_FOR_YOU_AREA'), '');
                redirect($_SERVER['PHP_SELF']);
            } else {
                $nomeArea = TRANS('NONE_FILTER');
                $terms .= TRANS('FILTERED_AREA') . ": {$nomeArea}";
            }


            if ((!isset($_POST['d_ini'])) || (!isset($_POST['d_fim']))) {
                $_SESSION['flash'] = message('info', '', TRANS('MSG_ALERT_PERIOD'), '');
                redirect($_SERVER['PHP_SELF']);
            } else {

                $d_ini = $_POST['d_ini'] . $hora_inicio;
                $d_ini = dateDB($d_ini);

                $d_fim = $_POST['d_fim'] . $hora_fim;
                $d_fim = dateDB($d_fim);

                if (isset($_POST['mesAtual'])) {
                    $d_ini = date("Y-m-01 00:00:00");
                    $d_fim = date("Y-m-d H:i:s");
                }

                if ($d_ini <= $d_fim) {

                    /* Query apenas para retornar os dados para o gráfico 1 - o agrupamento é diferente para a listagem */
                    $queryChart = $queryToChart . " AND o.oco_real_open_date >= '" . $d_ini . "' AND o.oco_real_open_date <= '" . $d_fim . "' 
                    GROUP BY status ORDER BY status, quantidade desc  ";
                    $resultadoChart = $conn->query($queryChart);

                    // /* Query apenas para retornar os dados para o gráfico 2 - o agrupamento é diferente para a listagem */
                    // $queryChart2 = $query . " AND o.data_fechamento >= '" . $d_ini . "' AND o.data_fechamento <= '" . $d_fim . "' AND o.data_atendimento IS NOT NULL 
                    // GROUP  BY s.sistema ORDER BY quantidade desc";
                    // $resultadoChart2 = $conn->query($queryChart2);

                    $query .= " AND o.oco_real_open_date >= '" . $d_ini . "' AND o.oco_real_open_date <= '" . $d_fim . "' 
							        GROUP BY status, area ORDER BY status, quantidade desc  ";
                    $resultado = $conn->query($query);
                    $linhas = $resultado->rowCount();

                    if ($linhas == 0) {
                        $_SESSION['flash'] = message('info', '', TRANS('MSG_NO_DATA_IN_PERIOD'), '');
                        redirect($_SERVER['PHP_SELF']);
                    } else {

                        ?>
                        <p><?= TRANS('TTL_PERIOD_FROM') . "&nbsp;" . dateScreen($d_ini, 1) . "&nbsp;" . TRANS('DATE_TO') . "&nbsp;" . dateScreen($d_fim, 1); ?></p>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <!-- table-hover -->
                                <caption><?= $terms; ?></caption>
                                <thead>
                                    <tr class="header table-borderless">
                                        <td class="line"><?= mb_strtoupper(TRANS('COL_STATUS')); ?></td>
                                        <td class="line"><?= mb_strtoupper(TRANS('SERVICE_AREA')); ?></td>
                                        <td class="line"><?= mb_strtoupper(TRANS('COL_AMOUNT')); ?></td>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $data = [];
                                    $data2 = [];
                                    
                                    $total = 0;
                                    foreach ($resultado->fetchall() as $row) {
                                        // $data[] = $row;
                                        ?>
                                        <tr>
                                            <td class="line"><?= $row['status']; ?></td>
                                            <td class="line"><?= $row['area']; ?></td>
                                            <td class="line"><?= $row['quantidade']; ?></td>
                                        </tr>
                                        <?php
                                        $total += $row['quantidade'];
                                    }

                                    foreach ($resultadoChart->fetchall() as $rowDataChart) {
                                        $data[] = $rowDataChart;
                                    }
                                    // foreach ($resultadoChart2->fetchall() as $rowDataChart2) {
                                    //     $data2[] = $rowDataChart2;
                                    // }
                                    
                                    $json = json_encode($data);
                                    // $json2 = json_encode($data2);
                                    ?>
                                </tbody>
                                <tfoot>
                                    <tr class="header table-borderless">
                                        <td colspan="2"><?= TRANS('TOTAL'); ?></td>
                                        <td><?= $total; ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="chart-container">
                            <canvas id="canvasChart1"></canvas>
                        </div>
                        <!-- <div class="chart-container">
                            <canvas id="canvasChart2"></canvas>
                        </div> -->
                        <?php
                        // var_dump([
                        //     'Query' => $query,
                        //     'Data' => $data,
                        //     'Json normal' => $json,
                        // ]);
                    }
                } else {
                    $_SESSION['flash'] = message('info', '', TRANS('MSG_COMPARE_DATE'), '');
                    redirect($_SERVER['PHP_SELF']);
                }
            }
        }
        ?>
    </div>
    <script src="../../includes/javascript/funcoes-3.0.js"></script>
    <script src="../../includes/components/jquery/jquery.js"></script>
    <script src="../../includes/components/jquery/datetimepicker/build/jquery.datetimepicker.full.min.js"></script>
    <script src="../../includes/components/bootstrap/js/bootstrap.min.js"></script>
    <script type="text/javascript" src="../../includes/components/chartjs/dist/Chart.min.js"></script>
    <script type="text/javascript" src="../../includes/components/chartjs/chartjs-plugin-colorschemes/dist/chartjs-plugin-colorschemes.js"></script>
    <script type="text/javascript" src="../../includes/components/chartjs/chartjs-plugin-datalabels/chartjs-plugin-datalabels.min.js"></script>
    <script type='text/javascript'>
        $(function() {
            /* Idioma global para os calendários */
            $.datetimepicker.setLocale('pt-BR');
            
            /* Calendários de início e fim do período */
            $('#d_ini').datetimepicker({
                format: 'd/m/Y',
                onShow: function(ct) {
                    this.setOptions({
                        maxDate: $('#d_fim').datetimepicker('getValue')
                    })
                },
                timepicker: false
            });
            $('#d_fim').datetimepicker({
                format: 'd/m/Y',
                onShow: function(ct) {
                    this.setOptions({
                        minDate: $('#d_ini').datetimepicker('getValue')
                    })
                },
                timepicker: false
            });


            $('#idSubmit').on('click', function() {
                $('.loading').show();
            });

            if (<?= $json ?> != 0) {
                showChart('canvasChart1');
            }

        });


        function showChart(canvasID) {
            var ctx = $('#' + canvasID);
            var dataFromPHP = <?= $json; ?>

            var labels = []; // X Axis Label
            var total = []; // Value and Y Axis basis

            for (var i in dataFromPHP) {
                // console.log(dataFromPHP[i]);
                // labels.push(dataFromPHP[i].operador);
                labels.push(dataFromPHP[i].status);
                total.push(dataFromPHP[i].quantidade);
            }

            var myChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        label: '<?= TRANS('total','',1); ?>',
                        data: total,
                        // backgroundColor: [
                        //     'rgba(255, 99, 132, 0.2)',
                        //     'rgba(54, 162, 235, 0.2)',
                        //     'rgba(255, 206, 86, 0.2)',
                        //     'rgba(75, 192, 192, 0.2)',
                        //     'rgba(153, 102, 255, 0.2)',
                        //     'rgba(255, 159, 64, 0.2)'
                        // ],
                        // borderColor: [
                        //     'rgba(255, 99, 132, 1)',
                        //     'rgba(54, 162, 235, 1)',
                        //     'rgba(255, 206, 86, 1)',
                        //     'rgba(75, 192, 192, 1)',
                        //     'rgba(153, 102, 255, 1)',
                        //     'rgba(255, 159, 64, 1)'
                        // ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    title: {
                        display: true,
                        text: '<?= TRANS('TICKETS_BY_STATUS','',1); ?>',
                    },
                    scales: {
                        yAxes: [{
                            display: false,
                            ticks: {
                                beginAtZero: true
                            }
                        }]
                    },
                    plugins: {
                        colorschemes: {
                            scheme: 'brewer.Paired12'
                        },
                        datalabels: {
                            display: function(context) {
                                return context.dataset.data[context.dataIndex] >= 1; // or !== 0 or ...
                            },
                            formatter: (value, ctx) => {
                                let sum = ctx.dataset._meta[0].total;
                                let percentage = (value * 100 / sum).toFixed(2) + "%";
                                return percentage;
                            }
                        },
                    },
                }
            });
        }

    </script>
</body>

</html>