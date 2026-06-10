import { startStimulusApp } from '@symfony/stimulus-bundle';
import GalleryController from './controllers/gallery_controller.js';

const app = startStimulusApp();
app.register('gallery', GalleryController);
