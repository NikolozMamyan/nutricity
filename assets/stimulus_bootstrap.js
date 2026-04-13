import { startStimulusApp } from '@symfony/stimulus-bundle';
import ClickCollectController from './controllers/click_collect_controller.js';
import LayoutController   from "./controllers/components/layout_controller.js";
import CatalogueController from './controllers/catalogue_controller.js';
import CartBadgeController from './controllers/cart_badge_controller.js';
import ProductShowController from "./controllers/product_show_controller.js";
import CategoryPageController from "./controllers/category_page_controller.js";

const app = startStimulusApp();
app.register('click-collect', ClickCollectController);
app.register("layout", LayoutController);
app.register("catalogue", CatalogueController);
app.register("cart-badge", CartBadgeController);
app.register("product-show", ProductShowController);
app.register("category-page", CategoryPageController);
