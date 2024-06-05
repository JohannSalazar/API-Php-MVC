<?php
// Clase usuarios
class usuarios
{
    // Datos de la tabla usuarios
    const NOMBRE_TABLA = "usuarios";
    const ID_USUARIO = "idUsuario";
    const NOMBRE = "nombre";
    const CONTRASENA = "contrasena";
    const CORREO = "correo";
    const CLAVE_API = "claveApi";
    // Estados de las peticiones
    const ESTADO_CREACION_EXITOSA = 1;
    const ESTADO_CREACION_FALLIDA = 2;
    const ESTADO_ERROR_BD = 3;
    const ESTADO_AUSENCIA_CLAVE_API = 4;
    const ESTADO_CLAVE_NO_AUTORIZADA = 5;
    const ESTADO_URL_INCORRECTA = 6;
    const ESTADO_FALLA_DESCONOCIDA = 7;
    const ESTADO_PARAMETROS_INCORRECTOS = 8;

    // Control de las peticiones GET
    //funcion para recibir peticiones get
    public static function get($peticion)
    {
        if (count($peticion) == 0) {
            return self::listarTodos();
        } else if (count($peticion) == 2 && $peticion[0] == 'correo') {
            // Obtener usuario por correo electrónico
            $correo = urldecode($peticion[1]);
            return self::obtenerUsuarioPorCorreo($correo);
        } else if (count($peticion) == 1) {
            // Obtener usuario por ID
            return self::listarPorId($peticion[0]);
        } else if (count($peticion) == 2) {
            // Obtener usuarios en un rango de IDs
            return self::listarPorRango($peticion[0], $peticion[1]);
        } else {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Faltan parámetros", 400);
        }
    }

    //peticiones post
    public static function post($peticion)
    {
        if ($peticion[0] == 'registro') {
            return self::registrar();
        } elseif ($peticion[0] == 'login') {
            return self::loguear();
        } else {
            throw new ExcepcionApi(self::ESTADO_URL_INCORRECTA, "URL mal formada", 400);
        }
    }

    //para actulizar aqui cacha el put
    public static function put($peticion)
    {
        if ($peticion[0] == 'actualizar') {
            return self::actualizarUsuario();
        } else {
            throw new ExcepcionApi(self::ESTADO_URL_INCORRECTA, "URL mal formada", 400);
        }
    }

   //si la url tiene eliminar aqui llega
    public static function delete($peticion)
    {
        if ($peticion[0] == 'eliminar') {
            return self::eliminarUsuario();
        } else {
            throw new ExcepcionApi(self::ESTADO_URL_INCORRECTA, "URL mal formada", 400);
        }
    }

      
    //usando post
    //cran unn registro con usuarios/registrar
    private static function registrar()
    {
        // Obtener los datos del cuerpo de la solicitud
        $cuerpo = file_get_contents('php://input');
        $usuario = json_decode($cuerpo);
        
        // Verificar que no estén vacíos los parámetros
        if (
            isset($usuario->nombre) &&
            isset($usuario->contrasena) &&
            isset($usuario->correo)
        ) {
            $nombre = $usuario->nombre;
            $contrasena = $usuario->contrasena;
            $contrasenaEncriptada = self::encriptarContrasena($contrasena);
            $correo = $usuario->correo;
            $claveApi = self::generarClaveApi();

            try {
                $pdo = ConexionBD::obtenerInstancia()->obtenerBD();

                // Verificar si el usuario ya existe
                $comando = "SELECT COUNT(" . self::ID_USUARIO . ") as cantidad FROM " . self::NOMBRE_TABLA .
                    " WHERE " . self::CORREO . "=?";

                $sentencia = $pdo->prepare($comando);
                $sentencia->bindParam(1, $correo);

                if ($sentencia->execute()) {
                    if ($sentencia->fetchColumn() > 0) {
                        throw new ExcepcionApi(self::ESTADO_CREACION_FALLIDA, "El correo ya existe");
                    }
                }

                // Insertar el nuevo usuario
                $comando = "INSERT INTO " . self::NOMBRE_TABLA . " (" .
                    self::NOMBRE . "," .
                    self::CONTRASENA . "," .
                    self::CORREO . "," .
                    self::CLAVE_API . ")" .
                    " VALUES(?,?,?,?)";

                $sentencia = $pdo->prepare($comando);

                $sentencia->bindParam(1, $nombre);
                $sentencia->bindParam(2, $contrasenaEncriptada);
                $sentencia->bindParam(3, $correo);
                $sentencia->bindParam(4, $claveApi);

                if ($sentencia->execute()) {
                    $idUsuario = $pdo->lastInsertId();

                    $respuesta = array();
                    $respuesta["estado"] = self::ESTADO_CREACION_EXITOSA;
                    $respuesta["mensaje"] = "Usuario creado correctamente";
                    $respuesta["idUsuario"] = $idUsuario;

                    return $respuesta;
                } else {
                    throw new ExcepcionApi(self::ESTADO_CREACION_FALLIDA, "Error al crear el usuario");
                }
            } catch (PDOException $e) {
                throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage());
            }
        } else {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Parametros incorrectos");
        }
    }


    //usa con post
    //solo manda el correo y contraseña a la url usuarios/login
    private static function loguear()
    {
        $cuerpo = file_get_contents('php://input');
        $usuario = json_decode($cuerpo);

        $correo = $usuario->correo;
        $contrasena = $usuario->contrasena;

        if (isset($correo) && isset($contrasena)) {
            try {
                $pdo = ConexionBD::obtenerInstancia()->obtenerBD();

                $comando = "SELECT idUsuario, nombre, contrasena, correo, claveApi FROM " . self::NOMBRE_TABLA . " WHERE " .
                    self::CORREO . "=?";

                $sentencia = $pdo->prepare($comando);
                $sentencia->bindParam(1, $correo);
                $sentencia->execute();

                if ($sentencia) {
                    if ($sentencia->rowCount() > 0) {
                        $row = $sentencia->fetch();

                        $idUsuario = $row['idUsuario'];
                        $nombre = $row['nombre'];
                        $contrasenaHash = $row['contrasena'];
                        $correo = $row['correo'];
                        $claveApi = $row['claveApi'];

                        if (self::validarContrasena($contrasena, $contrasenaHash)) {
                            $respuesta = array();
                            $respuesta['estado'] = 1;
                            $respuesta['mensaje'] = 'Usuario autenticado correctamente';
                            $respuesta['idUsuario'] = $idUsuario;
                            $respuesta['nombre'] = $nombre;
                            $respuesta['correo'] = $correo;
                            $respuesta['claveApi'] = $claveApi;

                            return $respuesta;
                        } else {
                            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Correo o contraseña inválidos");
                        }
                    } else {
                        throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Correo o contraseña inválidos");
                    }
                } else {
                    throw new ExcepcionApi(self::ESTADO_FALLA_DESCONOCIDA, "Error al obtener los datos del usuario");
                }
            } catch (PDOException $e) {
                throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage());
            }
        } else {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Correo o contraseña vacíos");
        }
    }
//usando metdo put
//usando la url usuarios/actualizar el id se lo das por json
    private static function actualizarUsuario()
{
    $cuerpo = file_get_contents('php://input');
    $usuario = json_decode($cuerpo);

    if (isset($usuario->idUsuario) && isset($usuario->nombre) && isset($usuario->contrasena) && isset($usuario->correo)) {
        $idUsuario = $usuario->idUsuario;
        $nombre = $usuario->nombre;
        $contrasena = $usuario->contrasena;
        $correo = $usuario->correo;

        // Encriptar la contraseña
        $contrasenaEncriptada = password_hash($contrasena, PASSWORD_DEFAULT);

        try {
            $pdo = ConexionBD::obtenerInstancia()->obtenerBD();

            $comando = "UPDATE " . self::NOMBRE_TABLA .
                " SET " . self::NOMBRE . "=?, " .
                self::CONTRASENA . "=?, " .
                self::CORREO . "=? " .
                "WHERE " . self::ID_USUARIO . "=?";

            $sentencia = $pdo->prepare($comando);

            $sentencia->bindParam(1, $nombre);
            $sentencia->bindParam(2, $contrasenaEncriptada);
            $sentencia->bindParam(3, $correo);
            $sentencia->bindParam(4, $idUsuario);

            $sentencia->execute();

            if ($sentencia->rowCount() > 0) {
                $respuesta = array();
                $respuesta['estado'] = 1;
                $respuesta['mensaje'] = 'Usuario actualizado correctamente';

                return $respuesta;
            } else {
                throw new ExcepcionApi(self::ESTADO_CREACION_FALLIDA, "Error al actualizar el usuario");
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage());
        }
    } else {
        throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Parámetros incorrectos");
    }
}

    private static function eliminarUsuario()
    {
        $cuerpo = file_get_contents('php://input');
        $usuario = json_decode($cuerpo);

        if (isset($usuario->idUsuario)) {
            $idUsuario = $usuario->idUsuario;
          

            try {
                $pdo = ConexionBD::obtenerInstancia()->obtenerBD();

                $comando = "DELETE FROM " 
                . self::NOMBRE_TABLA . " WHERE " 
                . self::ID_USUARIO . "=?";

                $sentencia = $pdo->prepare($comando);
                $sentencia->bindParam(1, $idUsuario);
                $sentencia->execute();

                if ($sentencia->rowCount() > 0) {
                    $respuesta = array();
                    $respuesta['estado'] = 1;
                    $respuesta['mensaje'] = 'Usuario eliminado correctamente';

                    return $respuesta;
                } else {
                    throw new ExcepcionApi(self::ESTADO_CREACION_FALLIDA, "Error al eliminar el usuario");
                }
            } catch (PDOException $e) {
                throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage());
            }
        } else {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Parametros incorrectos");
        }
    }


    //listar con get todos los usuarios
    //usando solo /usuarios
    private static function listarTodos()
    {
        try {
            $pdo = ConexionBD::obtenerInstancia()->obtenerBD();

            $comando = "SELECT idUsuario, nombre, contrasena, correo, claveApi FROM " . self::NOMBRE_TABLA;

            $sentencia = $pdo->prepare($comando);
            $sentencia->execute();

            if ($sentencia->rowCount() > 0) {
                $usuarios = $sentencia->fetchAll(PDO::FETCH_ASSOC);

                return $usuarios;
            } else {
                return array();
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage());
        }
    }

    //obtiene los datos de un usuario por medio del id
    
    private static function listarPorId($idUsuario)
    {
        try {
            $pdo = ConexionBD::obtenerInstancia()->obtenerBD();

            $comando = "SELECT idUsuario, nombre, contrasena, correo, claveApi FROM " . self::NOMBRE_TABLA . " WHERE " . self::ID_USUARIO . "=?";

            $sentencia = $pdo->prepare($comando);
            $sentencia->bindParam(1, $idUsuario);
            $sentencia->execute();

            if ($sentencia->rowCount() > 0) {
                $usuario = $sentencia->fetch(PDO::FETCH_ASSOC);

                return $usuario;
            } else {
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "El usuario no existe");
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage());
        }
    }
    //listar con get un rango de usuario por id 
    //usando /usuarios/2/4
    private static function listarPorRango($inicio, $fin)
    {
        $comando = "SELECT " .
            self::NOMBRE . "," .
            self::CONTRASENA . "," .
            self::CORREO . "," .
            self::CLAVE_API .
            " FROM " . self::NOMBRE_TABLA .
            " WHERE " . self::ID_USUARIO . " >= :inicio AND " . self::ID_USUARIO . " <= :fin";
    
        $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
    
        $sentencia->bindParam(':inicio', $inicio);
        $sentencia->bindParam(':fin', $fin);
    
        if ($sentencia->execute()) {
            return $sentencia->fetchAll(PDO::FETCH_ASSOC);
        } else {
            return null;
        }
    }

    //obtiene al usuario por medio del correo
    //ejemplo con un get /correo/Perez@gmail.com
    private static function obtenerUsuarioPorCorreo($correo)
    {
        try {
            $pdo = ConexionBD::obtenerInstancia()->obtenerBD();

            $comando = "SELECT idUsuario, nombre, contrasena, correo, claveApi FROM " . self::NOMBRE_TABLA . " WHERE " . self::CORREO . "=?";

            $sentencia = $pdo->prepare($comando);
            $sentencia->bindParam(1, $correo);
            $sentencia->execute();

            if ($sentencia->rowCount() > 0) {
                $usuario = $sentencia->fetch(PDO::FETCH_ASSOC);

                return $usuario;
            } else {
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "El usuario no existe");
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage());
        }
    }

    //encriptacion de la contraseña
    //es llamada en registro y en actualizar usuario para generar una contraseña encriptada
    private static function encriptarContrasena($contrasena)
    {
        if ($contrasena) {
            return password_hash($contrasena, PASSWORD_DEFAULT);
        } else {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Contraseña vacía");
        }
    }

    //genera la clave api usando md5
    private static function generarClaveApi()
    {
        return md5(microtime().rand());
    }
    //validacion de la contraseña para el login de un usuario
    private static function validarContrasena($contrasena, $contrasenaHash)
    {
        return password_verify($contrasena, $contrasenaHash);
    }


    public static function autorizar()
    {
        // Obtener las cabeceras de la solicitud HTTP
        $cabeceras = apache_request_headers();
    
        // Verificar si existe la clave "Authorization" en las cabeceras
        if (isset($cabeceras["Authorization"])) {
    
            // Obtener el valor de la clave "Authorization"
            $claveApi = $cabeceras["Authorization"];
    
            // Verificar si la clave de la API es válida
            if (usuarios::validarClaveApi($claveApi)) {
                // Obtener el ID del usuario asociado a la clave de la API
                return usuarios::obtenerIdUsuario($claveApi);
            } else {
                // Lanzar una excepción si la clave de la API no está autorizada
                throw new ExcepcionApi(
                    self::ESTADO_CLAVE_NO_AUTORIZADA,
                    "Clave de API no autorizada",
                    401
                );
            }
    
        } else {
            // Lanzar una excepción si no se encuentra la clave "Authorization" en las cabeceras
            throw new ExcepcionApi(
                self::ESTADO_AUSENCIA_CLAVE_API,
                utf8_encode("Se requiere Clave del API para autenticación")
            );
        }
    }

    //se valida la clave api para poder agregar una tarjeta
    private static function validarClaveApi($claveApi)
    {
        $comando = "SELECT COUNT(" . self::ID_USUARIO . ")" .
            " FROM " . self::NOMBRE_TABLA .
            " WHERE " . self::CLAVE_API . "=?";

        $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);

        $sentencia->bindParam(1, $claveApi);

        $sentencia->execute();

        return $sentencia->fetchColumn(0) > 0;
    }
    private static function obtenerIdUsuario($claveApi)
    {
        $comando = "SELECT " . self::ID_USUARIO .
            " FROM " . self::NOMBRE_TABLA .
            " WHERE " . self::CLAVE_API . "=?";

        $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);

        $sentencia->bindParam(1, $claveApi);

        if ($sentencia->execute()) {
            $resultado = $sentencia->fetch();
            return $resultado['idUsuario'];
        } else
            return null;
    }
}
