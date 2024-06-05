<?php
//clase tarjetas
class Tarjetas
{
    //datos de la tabla tarjetas
    const NOMBRE_TABLA = "tarjetas";
    const ID_TARJETA = "idTarjeta";
    const NUMERO_TARJETA = "numeroTarjeta";
    const FECHA_EXPIRACION = "fechaExpiracion";
    const ID_USUARIO = "idUsuario";

    const CODIGO_EXITO = 1;
    const ESTADO_EXITO = 1;
    const ESTADO_ERROR = 2;
    const ESTADO_ERROR_BD = 3;
    const ESTADO_ERROR_PARAMETROS = 4;
    const ESTADO_NO_ENCONTRADO = 5;

    // Método para obtener tarjetas
    //mandas el id de la tarjetas/1 pero solo tarjetas de la clave api de ese usuario
    public static function get($peticion)
    {
        // Llama al método autorizar de la clase usuarios para validar la clave API
        $idUsuario = usuarios::autorizar();

        if (empty($peticion[0])) {
            return self::obtenerTarjetas($idUsuario);
        } else {
            return self::obtenerTarjetas($idUsuario, $peticion[0]);
        }
    }

    // Método para crear una tarjeta
    //solo mandas el json con los datos a la url normal pero con post
    public static function post($peticion)
    {
        // Llama al método autorizar de la clase usuarios para validar la clave API
        $idUsuario = usuarios::autorizar();

        // Obtiene el cuerpo de la petición HTTP y lo decodifica como un objeto JSON
        $body = file_get_contents('php://input');
        $tarjeta = json_decode($body);

        // Llama al método crear para agregar la tarjeta a la base de datos
        $idTarjeta = self::crear($idUsuario, $tarjeta);

        http_response_code(201);
        return [
            "estado" => self::CODIGO_EXITO,
            "mensaje" => "Tarjeta creada",
            "id" => $idTarjeta
        ];
    }

    // Método para actualizar una tarjeta
    //mandas en el url el id de la tarjeta a modificar
    public static function put($peticion)
    {
        // Llama al método autorizar de la clase usuarios para validar la clave API
        $idUsuario = usuarios::autorizar();

        if (!empty($peticion[0])) {
            $body = file_get_contents('php://input');
            $tarjeta = json_decode($body);

            if (self::actualizar($idUsuario, $tarjeta, $peticion[0]) > 0) {
                http_response_code(200);
                return [
                    "estado" => self::CODIGO_EXITO,
                    "mensaje" => "Registro actualizado correctamente"
                ];
            } else {
                throw new ExcepcionApi(
                    self::ESTADO_NO_ENCONTRADO,
                    "La tarjeta a la que intentas acceder no existe",
                    404
                );
            }
        } else {
            throw new ExcepcionApi(
                self::ESTADO_ERROR_PARAMETROS,
                "Falta id",
                422
            );
        }
    }

    // Método para eliminar una tarjeta
    // solo mandas tarjertas/1 o el id de la tarjeta a eliminar
    
    public static function delete($peticion)
    {
        // Llama al método autorizar de la clase usuarios para validar la clave API
        $idUsuario = usuarios::autorizar();

        if (!empty($peticion[0])) {
            if (self::eliminar($idUsuario, $peticion[0]) > 0) {
                http_response_code(200);
                return [
                    "estado" => self::CODIGO_EXITO,
                    "mensaje" => "Registro eliminado correctamente"
                ];
            } else {
                throw new ExcepcionApi(
                    self::ESTADO_NO_ENCONTRADO,
                    "La tarjeta a la que intentas acceder no existe",
                    404
                );
            }
        } else {
            throw new ExcepcionApi(
                self::ESTADO_ERROR_PARAMETROS,
                "Falta id",
                422
            );
        }
    }

    // Método privado para obtener las tarjetas
    private static function obtenerTarjetas($idUsuario, $idTarjeta = NULL)
    {
        try {
            if (!$idTarjeta) {
                // Consulta SQL para obtener todas las tarjetas del usuario
                $comando = "SELECT * FROM " . self::NOMBRE_TABLA .
                    " WHERE " . self::ID_USUARIO . "=?";

                $sentencia = ConexionBD::obtenerInstancia()
                    ->obtenerBD()
                    ->prepare($comando);

                $sentencia->bindParam(1, $idUsuario, PDO::PARAM_INT);
            } else {
                // Consulta SQL para obtener una tarjeta específica del usuario
                $comando = "SELECT * FROM " . self::NOMBRE_TABLA .
                    " WHERE " . self::ID_TARJETA . "=? AND " .
                    self::ID_USUARIO . "=?";

                $sentencia = ConexionBD::obtenerInstancia()
                    ->obtenerBD()
                    ->prepare($comando);

                $sentencia->bindParam(1, $idTarjeta, PDO::PARAM_INT);
                $sentencia->bindParam(2, $idUsuario, PDO::PARAM_INT);
            }

            if ($sentencia->execute()) {
                http_response_code(200);
                return [
                    "estado" => self::ESTADO_EXITO,
                    "datos" => $sentencia->fetchAll(PDO::FETCH_ASSOC)
                ];
            } else {
                throw new ExcepcionApi(self::ESTADO_ERROR, "Se ha producido un error");
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage());
        }
    }

    // Método privado para crear una tarjeta
    private static function crear($idUsuario, $tarjeta)
    {
        if ($tarjeta) {
            try {
                $pdo = ConexionBD::obtenerInstancia()->obtenerBD();

                // Consulta SQL para insertar una nueva tarjeta en la base de datos
                $comando = "INSERT INTO " . self::NOMBRE_TABLA . " ( " .
                    self::NUMERO_TARJETA . "," .
                    self::FECHA_EXPIRACION . "," .
                    self::ID_USUARIO . ")" .
                    " VALUES(?,?,?)";

                $sentencia = $pdo->prepare($comando);

                $sentencia->bindParam(1, $numeroTarjeta);
                $sentencia->bindParam(2, $fechaExpiracion);
                $sentencia->bindParam(3, $idUsuario);

                $numeroTarjeta = $tarjeta->numeroTarjeta;
                $fechaExpiracion = $tarjeta->fechaExpiracion;

                $sentencia->execute();

                return $pdo->lastInsertId();
            } catch (PDOException $e) {
                throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage());
            }
        } else {
            throw new ExcepcionApi(
                self::ESTADO_ERROR_PARAMETROS,
                "Error en existencia o sintaxis de parámetros"
            );
        }
    }

    // Método privado para actualizar una tarjeta
    private static function actualizar($idUsuario, $tarjeta, $idTarjeta)
    {
        try {
            // Consulta SQL para actualizar los datos de una tarjeta específica
            $consulta = "UPDATE " . self::NOMBRE_TABLA .
                " SET " . self::NUMERO_TARJETA . "=?," .
                self::FECHA_EXPIRACION . "=? " .
                " WHERE " . self::ID_TARJETA . "=? AND " . self::ID_USUARIO . "=?";

            $sentencia = ConexionBD::obtenerInstancia()
                ->obtenerBD()
                ->prepare($consulta);

            $sentencia->bindParam(1, $numeroTarjeta);
            $sentencia->bindParam(2, $fechaExpiracion);
            $sentencia->bindParam(3, $idTarjeta);
            $sentencia->bindParam(4, $idUsuario);

            $numeroTarjeta = $tarjeta->numeroTarjeta;
            $fechaExpiracion = $tarjeta->fechaExpiracion;

            $sentencia->execute();

            return $sentencia->rowCount();
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage());
        }
    }

    // Método privado para eliminar una tarjeta
    private static function eliminar($idUsuario, $idTarjeta)
    {
        try {
            // Consulta SQL para eliminar una tarjeta específica
            $comando = "DELETE FROM " . self::NOMBRE_TABLA .
                " WHERE " . self::ID_TARJETA . "=? AND " .
                self::ID_USUARIO . "=?";

            $sentencia = ConexionBD::obtenerInstancia()
                ->obtenerBD()
                ->prepare($comando);

            $sentencia->bindParam(1, $idTarjeta);
            $sentencia->bindParam(2, $idUsuario);

            $sentencia->execute();

            return $sentencia->rowCount();
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage());
        }
    }
}
