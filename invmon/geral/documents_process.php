<?php session_start();
/*      Copyright 2020 Flávio Ribeiro

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
*/

if (!isset($_SESSION['s_logado']) || $_SESSION['s_logado'] == 0) {
    $_SESSION['session_expired'] = 1;
    echo "<script>top.window.location = '../../index.php'</script>";
    exit;
}

require_once __DIR__ . "/" . "../../includes/include_basics_only.php";
require_once __DIR__ . "/" . "../../includes/classes/ConnectPDO.php";

use includes\classes\ConnectPDO;

$conn = ConnectPDO::getInstance();

$auth = new AuthNew($_SESSION['s_logado'], $_SESSION['s_nivel'], 2, 2);

$post = $_POST;


$config = getConfig($conn);
// $rowLogado = getUserInfo($conn, $_SESSION['s_uid']);


$exception = "";
$screenNotification = "";
$data = [];
$data['success'] = true;
$data['message'] = "";
$data['cod'] = (isset($post['cod']) ? intval($post['cod']) : "");
$data['numero'] = (isset($post['numero']) ? intval($post['numero']) : "");
$data['action'] = $post['action'];
$data['field_id'] = "";


$data['document_name'] = (isset($post['document_name']) ? noHtml($post['document_name']) : "");
$data['document_quantity'] = (isset($post['document_quantity']) ? noHtml($post['document_quantity']) : "");
$data['document_box'] = (isset($post['document_box']) ? noHtml($post['document_box']) : "");
$data['related_model'] = (isset($post['related_model']) ? noHtml($post['related_model']) : "");
$data['aditional_info'] = (isset($post['aditional_info']) ? noHtml($post['aditional_info']) : "");


/* Checagem de preenchimento dos campos obrigatórios*/
if ($data['action'] == "new" || $data['action'] == "edit") {

    if ($data['document_name'] == "") {
        $data['success'] = false; 
        $data['field_id'] = "document_name";
    } elseif ($data['document_quantity'] == "") {
        $data['success'] = false; 
        $data['field_id'] = "document_quantity";
    } elseif ($data['aditional_info'] == "") {
        $data['success'] = false; 
        $data['field_id'] = "aditional_info";
    } 

    if ($data['success'] == false) {
        $data['message'] = message('warning', '', TRANS('MSG_EMPTY_DATA'), '');
        echo json_encode($data);
        return false;
    }

    if (!filter_var($data['document_quantity'], FILTER_VALIDATE_INT)) {
        $data['success'] = false; 
        $data['field_id'] = "document_quantity";
        $data['message'] = message('warning', '', TRANS('MSG_ERROR_WRONG_FORMATTED'), '');
        echo json_encode($data);
        return false;
    }
}


/* Processamento */
if ($data['action'] == "new") {

    $sql = "SELECT * FROM materiais WHERE mat_nome = '".$data['document_name']."' ";
    
    $res = $conn->query($sql);
    if ($res->rowCount()) {
        $data['success'] = false; 
        $data['field_id'] = "document_name";
        $data['message'] = message('warning', '', TRANS('MSG_RECORD_EXISTS'), '');
        echo json_encode($data);
        return false;
    }

    /* Verificação de CSRF */
    if (!csrf_verify($post)) {
        $data['success'] = false; 
        $data['message'] = message('warning', 'Ooops!', TRANS('FORM_ALREADY_SENT'),'');
        echo json_encode($data);
        return false;
    }

	$sql = "INSERT INTO materiais 
        (
            mat_nome, mat_qtd, mat_caixa, mat_modelo_equip, mat_obs, mat_data
        )
		VALUES 
        (
            '" . $data['document_name'] . "', '" . $data['document_quantity'] . "',
            " . dbField($data['document_box'], 'text') . ", " . dbField($data['related_model'],'int') . ", 
            " . dbField($data['aditional_info'], 'text') . ", '" . date('Y-m-d H:i:s') . "'
        )";
		
    try {
        $conn->exec($sql);
        $data['success'] = true; 
        $data['message'] = TRANS('MSG_SUCCESS_INSERT');
        
    } catch (Exception $e) {
        $data['success'] = false; 
        $data['message'] = TRANS('MSG_ERR_SAVE_RECORD') . "<br/>" . $sql;
        $_SESSION['flash'] = message('danger', '', $data['message'], '');
        echo json_encode($data);
        return false;
    }

} elseif ($data['action'] == 'edit') {

    $sql = "SELECT * FROM materiais WHERE mat_nome = '".$data['document_name']."' AND mat_cod <> '" . $data['cod'] . "'";
    
    $res = $conn->query($sql);
    if ($res->rowCount()) {
        $data['success'] = false; 
        $data['field_id'] = "model_name";
        $data['message'] = message('warning', '', TRANS('MSG_RECORD_EXISTS'), '');
        echo json_encode($data);
        return false;
    }

    if (!csrf_verify($post)) {
        $data['success'] = false; 
        $data['message'] = message('warning', 'Ooops!', TRANS('FORM_ALREADY_SENT'),'');
    
        echo json_encode($data);
        return false;
    }


    /* mat_nome, mat_qtd, mat_caixa, mat_modelo_equip, mat_obs, mat_data */
    $sql = "UPDATE materiais SET 
    
                mat_nome = '" . $data['document_name'] . "', 
                mat_qtd = '" . $data['document_quantity'] . "',
                mat_caixa = " . dbField($data['document_box'], 'text') . ",
                mat_modelo_equip = " . dbField($data['related_model'],'int') . ",
                mat_obs = '" . $data['aditional_info'] . "'
            WHERE 
                mat_cod = '" . $data['cod'] . "'";
            
    try {
        $conn->exec($sql);

        $data['success'] = true; 
        $data['message'] = TRANS('MSG_SUCCESS_EDIT');

    } catch (Exception $e) {
        $data['success'] = false; 
        $data['message'] = TRANS('MSG_ERR_DATA_UPDATE') . "<br />". $sql . "<br />" . $e->getMessage();
        $_SESSION['flash'] = message('danger', 'Ooops!', $data['message'], '');
        echo json_encode($data);
        return false;
    }
} elseif ($data['action'] == 'delete') {


 
    /* Sem restrições para excluir o registro */
    $sql = "DELETE FROM materiais WHERE mat_cod = '" . $data['cod'] . "'";

    try {
        $conn->exec($sql);
        $data['success'] = true; 
        $data['message'] = TRANS('OK_DEL');

        $_SESSION['flash'] = message('success', '', $data['message'] . $exception, '');
        echo json_encode($data);
        return false;
    } catch (Exception $e) {
        $exception .= "<hr>" . $e->getMessage() . "<hr>";
        $data['success'] = false; 
        $data['message'] = TRANS('MSG_ERR_DATA_REMOVE');
        $_SESSION['flash'] = message('danger', '', $data['message'] . $exception, '');
        echo json_encode($data);
        return false;
    }
}



$_SESSION['flash'] = message('success', '', $data['message'] . $exception, '');
echo json_encode($data);
return false;