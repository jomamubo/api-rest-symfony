<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Email;

use Symfony\Component\Routing\Annotation\Route;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User;
use App\Entity\Video;
use App\Services\JwtAuth;


use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;



class UserController extends AbstractController
{
    
    public function __construct() {}

    
    private function resjson($data){

        // Serializar datos con el servicio serializer
        // $json = $this->get('serializer')->serialize($data, 'json');
            
        // Tip : Inject SerializerInterface $serializer in the controller method
        // and avoid these 3 lines of instanciation/configuration
        $encoders = [new JsonEncoder()]; // If no need for XmlEncoder
        $normalizers = [new ObjectNormalizer()];
        $serializer = new Serializer($normalizers, $encoders);

        // Serialize el objecto en Json
        $json = $serializer->serialize($data, 'json', [
            'circular_reference_handler' => function ($object) {
                return $object->getId();
            }
        ]);


        // Response con httpfoundation
        $response = new Response();
        
        // Asignar contenido a la respuesta
        $response->setContent($json);

        // Indicar formato de respuesta
        $response->headers->set('Content-Type','application/json');

        // Devolver la respuesta
        return $response;

    }
    
    // Las rutas las estamos gestionando en el fichero routes.yaml
    // #[Route('/user', name: 'app_user')]

    public function index(ManagerRegistry $doc): Response
    {

        // $user_repo = $this->getDoctrine()->getRepository(User::class);
        // $video_repo = $this->getDoctrine()->getRepository(Video::class);
        $user_repo = $doc->getRepository(User::class);
        $video_repo = $doc->getRepository(Video::class);

        // Devuelve todos los usuario, con sus subclases anidadas
        $users = $user_repo->findAll();
        $user = $user_repo->find(1);

        $videos = $video_repo->findAll();
        
        $data = [
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/UserController.php',
        ];
        
        /*
        foreach($users as $user){
            echo "<h1>{$user->getName()} {$user->getSurname()}</h1>";

            foreach($user->getVideos() as $video){
                echo "<p>{$video->getTitle()} - {$video->getUser()->getEmail()}</p>";
            }
        }
        */

        // var_dump($user);
        // die();

        return $this->resjson($videos);
        

    }
    
/*
    public function index(){
        return $this->json([
            'message'=>'Bienvenido a tu nuevo controlador',
            'path'=>'src/Constroller/UserController.php'
        ]);
    }
*/

    public function create(Request $request, ManagerRegistry $doc){
        //Recoger los datos por POST
        $json = $request->get('json', null);

        // return $this->resjson($json);

        // Decodificar el JSON
        $params = json_decode($json);

        // Hacer respuesta por defecto
        $data = [
            'status' => 'error',
            'code' => 200,
            'message' => 'El usuario no se ha creado'
        ];

        // Comprobar y validar datos
        if($json != null){

            $name = (!empty($params->name)) ? $params->name :null;
            $surname = (!empty($params->surname)) ? $params->surname :null;
            $email = (!empty($params->email)) ? $params->email :null;
            $password = (!empty($params->password)) ? $params->password :null;

            $validator = Validation::createValidator();
            $validate_email = $validator->validate($email, [
                new Email()
            ]);

            if(!empty($email) && count($validate_email) == 0 && !empty($password) && !empty($name) && !empty($surname)){
                /*
                $data = [
                    'status' => 'success',
                    'code' => 200,
                    'message' => 'VALIDACION CORRECTA'
                ];
                */

                // Si la validacion es correcta, crear el objeto del usuario
                $user = new User();
                $user->setName($name);
                $user->setSurname($surname);
                $user->setEmail($email);
                $user->setRole('ROLE_USER');
                $user->setCreatedAt(new \Datetime('now'));

                // Cifrar la pass
                $pwd = hash('sha256', $password);

                $user->setPassword($pwd);

                $data = $user;

                // Comprobar si el usuario existe, control de duplicados
                
                $em = $doc->getManager();
                $user_repo = $doc->getRepository(User::class);
                $isset_user = $user_repo->findBy(array(
                    'email'=>$email
                ));

                // Si no exista, guardar en BBDD
                if(count($isset_user) == 0){
                    // guardo el usuario
                    $em->persist($user);
                    $em->flush();   // Ejecuta las consultas directamente en la BBDD.

                    $data = [
                        'status' => 'success',
                        'code' => 200,
                        'message' => 'Usuario creado correctamente',
                        'user' => $user
                    ];
                }else{
                    $data = [
                        'status' => 'error',
                        'code' => 400,
                        'message' => 'El usuario ya existe.'
                    ];
                }

            }

        }


        
        // Hacer respuesta en JSON
        return $this->resjson($data);
        // return new JsonResponse($data);
        

    }

    public function login(Request $request, JwtAuth $jwt_auth){
        // Recibir los datos por post
        $json = $request->get('json', null);
        $params = json_decode($json);

        // Array por defecto para devolver
        $data = [
            'status' => 'error',
            'code' => 200,
            'mensaje' => 'El usuario no se ha podido identificar'
        ];

        // Comprobar y validar datos
        if($json != null){

            $email = (!empty($params->email)) ? $params->email: null;
            $password = (!empty($params->password)) ? $params->password: null;
            $gettoken = (!empty($params->gettoken)) ? $params->gettoken: null;
               
            $validator = Validation::createValidator();
            $validate_email = $validator->validate($email, [
                new Email()
            ]);

            if(!empty($email) && !empty($password) && count($validate_email) == 0){
                
                // Cifrar la pass
                $pwd = hash('sha256', $password);        

                // Si todo es valido, llamaremos a un servicio para identificar el usuario (jwt), devuelve token u objeto
                
                if($gettoken){
                    $signup = $jwt_auth->signup($email, $pwd, $gettoken);
                }else{
                    $signup = $jwt_auth->signup($email, $pwd);
                }        

                return new JsonResponse($signup);

            }

        }


        // Si nos devuelve bien los datos, respuesta
        return $this->resjson($data);

    }

    public function edit(Request $request, JwtAuth $jwt_auth, ManagerRegistry $doc ){
        // Recoger la cabecera de autenticación
        $token = $request->headers->get('Authorization');

        // Crear un método para comprobar si el token es correcto
        $authCheck = $jwt_auth->checkToken($token);

        // Repuesta por defecto
        $data = [
            'status' => 'error',
            'code' => 400,
            'mensaje' => 'Usuario no actualizado'
        ];

        // Si es correcto, hace la actualización del usuario
        if($authCheck){

            // Actualizar el usuario


            // Conseguir el entityManager
            $em = $doc->getManager();

            // Conseguir datos usuario identificado
            $identity = $jwt_auth->checkToken($token, true);
            
            // var_dump($identity);
            // die();

            // Conseguir el usuario a actualizar
            $user_repo = $doc->getRepository(User::class);
            $user = $user_repo->findOneBy([
                'id' => $identity->sub
            ]);

            // Recoger los datos por post
            $json = $request->get('json', null);
            $params = json_decode($json);

            // Comprobar y validad los datos
            $name = (!empty($params->name)) ? $params->name :null;
            $surname = (!empty($params->surname)) ? $params->surname :null;
            $email = (!empty($params->email)) ? $params->email :null;

            $validator = Validation::createValidator();
            $validate_email = $validator->validate($email, [
                new Email()
            ]);

            if(!empty($email) && count($validate_email) == 0 && !empty($name) && !empty($surname)){

                // Asignar nuevos datos al objecto del usuario
                $user->setEmail($email);
                $user->setName($name);
                $user->setSurname($surname);

                // Comprobar duplicados
                $isset_user = $user_repo->findBy([
                    'email'=>$email
                ]);

                // Si el usuario no existe, o si el usuerio es el mismo que ya hay identificado
                if(count($isset_user)==0 || $identity->email == $email){
                    // Guardar cambios en la BBDD
                    $em->persist($user);
                    $em->flush();
    
                    $data = [
                        'status' => 'success',
                        'code' => 200,
                        'mensaje' => 'Usuario actualizado',
                        'user' => $user
                    ];
                }else{
                    $data = [
                        'status' => 'error',
                        'code' => 400,
                        'mensaje' => 'No puede usar ese email'
                    ];
                }
            }
           
        }

        return $this->resjson($data);
    }



}
