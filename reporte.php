<?php
require('fpdf/fpdf.php');

// Incluir el archivo de conexión a la base de datos
require_once 'datos/ConexionBD.php';

class PDFReport
{
    public $estado;

    public function imprimir($datos)
    {
        // Iniciar el almacenamiento en búfer de salida
        ob_start();

        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 12);

        $pdf->Cell(0, 10, 'Reporte de Usuarios y Tarjetas', 0, 1, 'C');
        $pdf->Ln(10);

        if (empty($datos)) {
            $pdf->Cell(0, 10, 'No se encontraron datos.', 0, 1, 'C');
        } else if (!is_array($datos)) {
            $pdf->Cell(0, 10, 'Formato de datos incorrecto.', 0, 1, 'C');
        } else {
            $pdf->Cell(20, 10, 'ID', 1);
            $pdf->Cell(40, 10, 'Nombre', 1);
            $pdf->Cell(60, 10, 'Correo', 1);
            $pdf->Cell(40, 10, 'Tarjeta', 1);
            $pdf->Cell(30, 10, 'Expiracion', 1);
            $pdf->Ln();

            foreach ($datos as $fila) {
                if (is_array($fila)) {
                    $pdf->Cell(20, 10, isset($fila['idUsuario']) ? $fila['idUsuario'] : '', 1);
                    $pdf->Cell(40, 10, isset($fila['nombre']) ? $fila['nombre'] : '', 1);
                    $pdf->Cell(60, 10, isset($fila['correo']) ? $fila['correo'] : '', 1);
                    $pdf->Cell(40, 10, isset($fila['numeroTarjeta']) ? $fila['numeroTarjeta'] : 'N/A', 1);
                    $pdf->Cell(30, 10, isset($fila['fechaExpiracion']) ? $fila['fechaExpiracion'] : 'N/A', 1);
                    $pdf->Ln();
                } else {
                    $pdf->Cell(0, 10, 'Error en la estructura de los datos.', 0, 1, 'C');
                    break;
                }
            }
        }

        // Limpiar el búfer de salida y cerrar
        ob_end_clean();

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="reporte_usuarios_tarjetas.pdf"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        // Salida del contenido del PDF
        $pdf->Output('I', 'reporte_usuarios_tarjetas.pdf');
    }
}

// Verificar la ruta y el formato de consulta
if (isset($_GET['formato']) && $_GET['formato'] === 'pdf') {
    try {
        // Conectar a la base de datos usando la conexión existente
        $conexion = ConexionBD::obtenerInstancia()->obtenerBD();

        // Realizar la consulta con LEFT JOIN
        $query = "
            SELECT u.idUsuario, u.nombre, u.correo, t.numeroTarjeta, 
                   IFNULL(DATE_FORMAT(t.fechaExpiracion, '%m/%Y'), 'N/A') as fechaExpiracion
            FROM usuarios u
            LEFT JOIN tarjetas t ON u.idUsuario = t.idUsuario
        ";
        $stmt = $conexion->prepare($query);
        $stmt->execute();

        $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Depuración: verificar los datos obtenidos
        if (empty($datos)) {
            error_log('No se encontraron datos en la consulta.');
        } else {
            error_log('Datos obtenidos: ' . print_r($datos, true));
        }

        // Crear un nuevo reporte y enviar los datos
        $reporte = new PDFReport();
        $reporte->imprimir($datos);

    } catch (PDOException $e) {
        echo 'Error de conexión: ' . $e->getMessage();
        error_log('Error de conexión: ' . $e->getMessage());
    }
} else {
    // No hacer nada si el formato no es PDF para evitar mensajes no deseados
    // Esto es útil si solo quieres que el PDF se genere para la ruta específica
}
?>
