<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Email;

 use Knp\Component\Pager\PaginatorInterface;

use Symfony\Component\Routing\Annotation\Route;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User;
use App\Entity\Video;
use App\Services\JwtAuth;


use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class VideoController extends AbstractController
{

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


    public function index(): Response
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/VideoController.php',
        ]);
    }

    public function create(Request $request, JwtAuth $jwt_auth, ManagerRegistry $doc, $id = null){
        
        $data = [
            'status' => 'error',
            'code' => 400,
            'mensaje' => 'El video no ha podido crearse'
        ];

        // Recoger el token
        $token = $request->headers->get('Authorization', null);

        // Comprobar si es correcto
        $authCheck = $jwt_auth->checkToken($token);

        if($authCheck){
            // Recoger datos por POST
            $json = $request->get('json',null);
            $params = json_decode($json);

            // Recoger datos de usuario identificado
            $identity = $jwt_auth->checkToken($token, true);

            // Comprobar y validar datos
            if(!empty($json)){

                $user_id = ($identity->sub != null) ? $identity->sub : null;
                $title = (!empty($params->title)) ? $params->title: null;
                $description = (!empty($params->description)) ? $params->description: null;
                $url = (!empty($params->url)) ? $params->url: null;

                if(!empty($user_id) && !empty($title)){
                    // Guardar el nuevo video favorito en la bd
                    $em = $doc->getManager();
                    $user = $em->getRepository(User::class)->findOneBy([
                        'id'=>$user_id
                    ]);

                    // Si no me llega ID, damos de alta el video
                    if($id == null){
                        // Crear y guardar el objeto

                        $video = new Video();
                        $video->setUser($user);
                        $video->setTitle($title);
                        $video->setDescripcion($description);
                        $video->setUrl($url);
                        $video->setStatus('normal');

                        $createdAt = new \Datetime('now');
                        $updatedAt = new \Datetime('now');
                        
                        $video->setCreatedAt($createdAt);
                        $video->setUpdatedAt($updatedAt);
                        
                        // Guardar en BBDD
                        $em->persist($video);
                        $em->flush();
                        
                        $data = [
                            'status' => 'success',
                            'code' => 200,
                            'mensaje' => 'El video se ha guardado',
                            'video' => $video
                        ];
                    }else{
                        // var_dump("Entramos por actualizacion");
                        // var_dump($id);
                        var_dump($identity->sub);

                        // Si me llega el id, actualizamos el video

                        $video = $doc->getRepository(Video::class)->findOneBy([
                            'id'=>$id,
                            'user'=>$identity->sub
                        ]);

                        if($video && is_object($video)){

                            // Guardar el nuevo video en la BBDD

                            $video->setTitle($title);
                            $video->setDescripcion($description);
                            $video->setUrl($url);
                            $video->setStatus('normal');
                            $updatedAt = new \Datetime('now');
                            $video->setUpdatedAt($updatedAt);

                            $em->persist($video);
                            $em->flush();

                            $data = [
                                'status' => 'success',
                                'code' => 200,
                                'mensaje' => 'El video se ha actualizado',
                                'video' => $video
                            ];
                        }

                    }                       
                }
            }

        }
        
        // Devolver respuesta

        return $this->resjson($data);
    }

    public function videos(Request $request, JwtAuth $jwt_auth, ManagerRegistry $doc, PaginatorInterface $paginator){
            
        // Recoger la cabecera de autenticacion
        $token = $request->headers->get('Authorization');

        // Comprobar el token
        $authCheck = $jwt_auth->checkToken($token);

        // Si es válido
        if($authCheck){

            // Conseguir identidad del usuario
            $identity = $jwt_auth->checkToken($token, true);
            
            $em = $doc->getManager();

//var_dump($identity->sub);

            // Hacer consulta para paginar
            $dql = "SELECT v FROM App\Entity\Video v WHERE v.user = {$identity->sub} ORDER BY v.id DESC"; 
            $query = $em->createQuery($dql);
            
            // Recoger el parámetro page de la url
            $page = $request->query->getInt('page', 1);
            $items_per_page = 5;

            // Invocar paginación
            $pagination = $paginator->paginate($query, $page, $items_per_page);
            $total = $pagination->getTotalItemCount();

 //var_dump(ceil($total / $items_per_page));
 //var_dump($$page);


            // Preparar array de datos para devolver
            $data = array(
                'status' => 'success',
                'code' => 200,
                'total_items_count'=> $total,
                'page_actual'=> $page,
                'items_per_page'=> $items_per_page,
                'total_pages'=> ceil($total / $items_per_page),
                'videos'=> $pagination,
                'user_id'=> $identity->sub
            );

        }else{

            // Si falla devolver

            $data = array(
                'status' => 'error',
                'code' => 404,
                'mensaje' => 'No se puedes listar los videos en este momento'
            );
        }

        return $this->resjson($data);
    }

    public function detail(Request $request, JwtAuth $jwt_auth, ManagerRegistry $doc, $id=null){
        
        // Sacar el token y comprobar si es correcto
        $token = $request->headers->get('Authorization');
        $authCheck = $jwt_auth->checkToken($token);

        $data = array(
            'status' => 'error',
            'code' => 404,
            'mensaje' => 'No se puede mostrar detalles en este momento'
        );


        if($authCheck){
            // Sacar la identidad del usuario
            $identity = $jwt_auth->checkToken($token, true);

            // Sacar el objecto del video en base al id
            $video = $doc->getRepository(Video::class)->findOneBy([
                'id'=>$id
            ]);

            // Comprobar si el video existe y es propiedad del usuario identificado
            if($video && is_object($video) && $identity->sub == $video->getUser()->getId()){
                $data = [
                    'status' => 'success',
                    'code' => 200, 
                    'video' => $video
                ];
            }

        }

        // Devolver una respuesta
        return $this->resjson($data);
    }

    public function remove(Request $request, JwtAuth $jwt_auth, ManagerRegistry $doc, $id=null){

        // Recoger el token del usuario
        $token = $request->headers->get('Authorization');
        $authCheck = $jwt_auth->checkToken($token);


        // Devolver la respuesta
        $data = [
                    'status' => 'error',
                    'code' => 404, 
                    'video' => 'Video no encontrado'
                ];
        
        if($authCheck){
            $identity = $jwt_auth->checkToken($token, true);
            $em = $doc->getManager();
            $video = $doc->getRepository(Video::class)->findOneBy(['id'=>$id]);

            // Comprobar si el video existe y es propiedad del usuario identificado
            if($video && is_object($video) && $identity->sub == $video->getUser()->getId()){

                // Elimina el vidoe de la BBDD
                $em->remove($video);
                // Persiste los cambios
                $em->flush();

                $data = [
                    'status' => 'success',
                    'code' => 200, 
                    'video' => $video
                ];

            }
        }


        return $this->resjson($data);


    }
}
