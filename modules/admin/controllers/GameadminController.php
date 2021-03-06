<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 16.03.15
 * Time: 10:03
 */

namespace app\modules\admin\controllers;


use app\models\Category;
use app\models\Game;
use app\models\GameSearch;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

class GameadminController extends BaseAdminController{

    public $defaultAction = 'index';

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                //'only' => ['logout'],
                'rules' => [
                    [
                        //'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /*
     * list of all games
     */
    public function actionIndex(){

        $searchModel = new GameSearch();

        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Creates a new Game model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $dir = Yii::getAlias('@app/');

        $model = new Game();

        $listCategory = ArrayHelper::map(Category::find()->all(),'id','title');

        $model->setScenario('create');

        //send POST - request to update
        if (Yii::$app->request->isPost) {

            //old values
            $img = $model->img;
            $file = $model->file;

            //load data from POST
            $model->load(Yii::$app->request->post());

            //upload selected files
            $model->file = UploadedFile::getInstance($model, 'file');//flash game
            $model->img = UploadedFile::getInstance($model, 'img');//image of game

            if ($model->validate()) {

                if(empty($model->updated_at)){
                    $model->updated_at = time();
                }else{
                    $model->updated_at = strtotime($model->updated_at);
                }

                if($model->file){
                    $model->file->saveAs($dir.Yii::$app->params['upload_flash'] . $model->alias . '.' . $model->file->extension);
                    $model->file = $model->alias . '.' . $model->file->extension;
                }else{
                    $model->file = $file;
                }

                if($model->img){
                    $model->img->saveAs($dir.Yii::$app->params['upload_image'] . $model->alias  . '.' . $model->img->extension);
                    $model->img = $model->alias  . '.' . $model->img->extension;
                }else{
                    $model->img = $img;
                }

                $model->save();

                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        return $this->render('create', [
            'model' => $model,
            'listCategory'=>$listCategory,
        ]);
    }

    /**
     * Updates an existing Game model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {

        $dir = Yii::getAlias('@app/');

        $listCategory = ArrayHelper::map(Category::find()->all(),'id','title');

        $model = $this->findModelId($id);

        //send POST - request to update
        if (Yii::$app->request->isPost) {

            //old values
            $img = $model->img;
            $file = $model->file;

            $update_time_old = $model->updated_at;

            //load data from POST
            $model->load(Yii::$app->request->post());

            //upload selected files
            $model->file = UploadedFile::getInstance($model, 'file');//flash game
            $model->img = UploadedFile::getInstance($model, 'img');//image of game

            //если мы указали дату поста принудительно, то используем её для установки значения
            if($model->updated_at){
                $model->updated_at = $update_time_old;
            }else{
                // если мы НЕ указывали значение, тогда установим текущее время
                $model->updated_at = time();
            }

            if ($model->validate()) {

                if($model->file){
                    $model->file->saveAs($dir.Yii::$app->params['upload_flash']  . $model->alias . '.' . $model->file->extension);
                    $model->file = $model->alias  . '.' . $model->file->extension;
                }else{
                    $model->file = $file;
                }

                if($model->img){
                    $prefix = uniqid('img_');
                    $model->img->saveAs($dir.Yii::$app->params['upload_image']  . $model->alias  . '.' . $model->img->extension);
                    $model->img = $model->alias  . '.' . $model->img->extension;
                }else{
                    $model->img = $img;
                }

                $model->save();

                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        return $this->render('update', [
            'model' => $model,
            'listCategory'=>$listCategory,
        ]);
    }

    /**
     * Deletes an existing Game model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $this->findModelId($id)->delete();

        return $this->redirect(['index']);
    }

    protected function findModelId($id)
    {
        if (($model = Game::findOne(['id'=>$id])) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    public function actionView($id){

        //find game by alias
        $model = $this->findModelId($id);

        //find others games from this category(similar-games)
        $query = Game::find()
            ->joinWith('gameCategory')
            ->selectMain()
            ->Where(['not in', 'tbl_game_category.game_id', $model->id])
            ->andWhere(['in', 'tbl_game_category.category_id', $model->getCategorys()])
            ->publish()
            ->timepublish()
            ->limit(Yii::$app->params['count_similar_games_page_view'])
            ->orderBy('tbl_game.updated_at DESC');


        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => false,
        ]);

        return $this->render('@app/views/game/view', [
            'model' => $model,
            'similarDataProvider'=>$dataProvider,
        ]);
    }
} 