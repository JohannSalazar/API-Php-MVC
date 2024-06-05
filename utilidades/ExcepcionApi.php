<?php

/**
 * Excepcion personalizada para el envioo del estado
 */
class ExcepcionApi extends Exception
{
    public $estado;

    public function __construct($estado, $mensaje, $codigo = 400)
    {
        $this->estado = $estado;
        $this->message = $mensaje;
        $this->code = $codigo;
    }

}