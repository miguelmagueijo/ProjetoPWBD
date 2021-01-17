<?php
    include($_SERVER["DOCUMENT_ROOT"]."/ProjetoPWBD/scripts/php/major_functions.php");
    if (!checkIfClient()) {
        gotoIndex();
    }
    if (isset($_POST["ni_startdate"]) && isset($_POST["ni_starttime"]) && isset($_POST["ni_vehicle"])) {
        
        $inspection = array(
            "startdt" => date_create_from_format("Y-m-d-H:i", $_POST["ni_startdate"]."-".$_POST["ni_starttime"]),
            "vehicle" => $_POST["ni_vehicle"]
        );

        include("basedados.h");
        $query = "SELECT idVeiculo FROM veiculo_utilizador WHERE idUtilizador = :userid AND idVeiculo = :id;";
        $stmt = $dbo->prepare($query);
        $stmt->bindValue("userid", LOGIN_DATA["id"]);
        $stmt->bindValue("id", $inspection["vehicle"]);
        $stmt->execute();
        if ($stmt->rowCount() != 1) {
            die("Veiculo invalido (não existe ou não pertence ao utilizador)");
        }
        $query = "
            SELECT v.id, v.matricula, vu.idUtilizador AS idUser, cv.id AS idCategory
            FROM veiculo AS v
                INNER JOIN Veiculo_Utilizador AS vu ON v.id = vu.idVeiculo
                INNER JOIN CategoriaVeiculo AS cv ON v.idCategoria = cv.id
            WHERE v.id = :id AND vu.idUtilizador = :userid
        ";
        $stmt = $dbo->prepare($query);
        $stmt->bindValue("id", $_POST["ni_vehicle"]);
        $stmt->bindValue("userid", LOGIN_DATA["id"]);
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            die("Não há veiculo registado com o ID pedido");
        } else {
            $vehicle = $stmt->fetch();
        }
        $query = "
            SELECT i.idVeiculo, i.horaInicio AS dateStart, i.horaFim AS dateEnd, i.idLinha AS linha
            FROM Inspecao AS i
                INNER JOIN LinhaInspecao AS li ON i.idLinha = li.id
                INNER JOIN CategoriaVeiculo AS cv ON li.idCategoria = cv.id
            WHERE cv.id = :id;
        ";
        $stmt = $dbo -> prepare($query);
        $stmt -> bindValue("id", $vehicle["idCategory"]);
        $stmt -> execute();
        $invalidDates = $stmt -> fetchAll();
        
        $query = "
            SELECT *
            FROM LinhaInspecao
            WHERE idCategoria = :id;
        ";
        $stmt = $dbo -> prepare($query);
        $stmt -> bindValue("id", $vehicle["idCategory"]);
        $stmt -> execute();
        $lines = $stmt -> fetchAll();

        if ($vehicle["idCategory"] == 1) {
            $intervalString = "PT30M";
        } else {
            $intervalString = "PT1H";
        }
        $start = new DateTime(date("Y-m-d H:i:s", strtotime("09:00:00")));
        $end = new DateTime(date("Y-m-d H:i:s", strtotime("17:00:00")));
        $dateStart = date_add($start, new DateInterval("P2D"));
        $dateEnd = date_add($end, new DateInterval("P30D"));
        $interval = new DatePeriod($dateStart, new DateInterval($intervalString), $dateEnd);
        $datesAvailable = array();
        foreach ($lines as $line) {
            $linha = $line["id"];
            foreach($interval as $date) {
                $weekDay = $date -> format("w");
                $hour = $date -> format("H");
                if ($weekDay == 0) {
                    continue;
                } elseif ($weekDay == 6 && $hour > 13) {
                    continue;
                } else {
                    if ($hour < 9 || $hour > 18 ) {
                        continue;
                    }
                }
                $isValid = true;
                foreach($invalidDates as $invDate) {
                    if ($invDate["idVeiculo"] == $inspection["vehicle"]) {
                        die("Este veiculo ja tem inspeção marcada");
                    }
                    if ( $date == new DateTime($invDate["dateStart"]) && $linha == $invDate["linha"]) {
                        $isValid = false;
                        break;
                    }
                }
                
                if ($isValid) {
                    array_push($datesAvailable, array("date" => $date, "linha" => $linha));
                }
                
            }
        }

        
        $valido = false;
        foreach ($datesAvailable as $d) {
            if ($inspection["startdt"] == $d["date"]) {
                $valido = true;
                $validline = $d["linha"];
                break;
            }
        }
        if (!$valido) {
            echo "Data Invalida<br>";
            echo "<a href='#' onclick='history.back()'>Voltar atrás</a>";
            die();
        }
        echo $valido ? "Valido na linha $validline" : "Invalido";
        $query = "
            INSERT INTO inspecao (horaInicio, horaFim, idVeiculo, idLinha) VALUES (:horaInicio, :horaFim, :idVeiculo, :idLinha);
        ";
        $stmt = $dbo->prepare($query);
        $stmt->bindValue("horaInicio", $inspection["startdt"]->format("Y-m-d H:i:00"));
        $stmt->bindValue("horaFim", date_add($inspection["startdt"], new DateInterval($intervalString))->format("Y-m-d H:i:00"));
        $stmt->bindValue("idVeiculo", $inspection["vehicle"]);
        $stmt->bindValue("idLinha", $validline);
        $stmt->execute();

        header("Location: /ProjetoPWBD/customer");
        die();
    }
    header("Location: /ProjetoPWBD/vehicle");
    die();
?>