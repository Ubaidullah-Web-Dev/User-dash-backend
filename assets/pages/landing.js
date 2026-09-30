// Port of app/page.tsx (AOS scroll animations; motion effects come from the x-magnetic/x-spotlight/x-counter directives)
import AOS from 'aos';
import 'aos/dist/aos.css';

AOS.init({
    duration: 600,
    once: false,
    mirror: true,
    easing: 'ease-in-out-cubic',
    offset: 50,
});
