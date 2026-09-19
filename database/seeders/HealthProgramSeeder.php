<?php

namespace Database\Seeders;

use App\Models\ChickenType;
use App\Models\HealthProgram;
use Illuminate\Database\Seeder;

class HealthProgramSeeder extends Seeder
{
    public function run(): void
    {
        $chair = ChickenType::where('name', 'Chair')->first();
        $pondeuse = ChickenType::where('name', 'Pondeuse')->first();

        // ====================================================================
        // PROGRAMMES COMMUNS — applicable à tous les types (chicken_type_id = null)
        // ====================================================================
        $communs = [
            [
                'treatment_name' => 'Multivitamines & Électrolytes (arrivée)',
                'treatment_type' => 'vitamine',
                'scheduled_day' => 1,
                'administration_route' => 'eau',
                'dosage' => '1 g/L pendant 3 jours',
                'withdrawal_days' => 0,
                'is_mandatory' => false,
                'notes' => 'Administrer dès l\'arrivée des poussins pour réduire le stress du transport et favoriser l\'hydratation.',
            ],
            [
                'treatment_name' => 'Vitamines A, D3, E (stress post-vaccination)',
                'treatment_type' => 'vitamine',
                'scheduled_day' => 8,
                'administration_route' => 'eau',
                'dosage' => '1 mL/L pendant 2 jours',
                'withdrawal_days' => 0,
                'is_mandatory' => false,
                'notes' => 'Soutien immunitaire post-vaccination contre la bronchite infectieuse.',
            ],
        ];

        foreach ($communs as $programme) {
            HealthProgram::firstOrCreate(
                [
                    'organisation_id' => null,
                    'farm_id' => null,
                    'chicken_type_id' => null,
                    'treatment_name' => $programme['treatment_name'],
                    'scheduled_day' => $programme['scheduled_day'],
                ],
                $programme,
            );
        }

        // ====================================================================
        // PROGRAMME SANITAIRE — POULETS DE CHAIR (cycle ~45 jours)
        // ====================================================================
        $programmesChair = [
            // --- VACCINS OBLIGATOIRES ---
            [
                'treatment_name' => 'Bronchite Infectieuse H120 (primo-vaccination)',
                'treatment_type' => 'vaccin',
                'scheduled_day' => 7,
                'administration_route' => 'eau',
                'dosage' => '1 dose/sujet',
                'withdrawal_days' => 0,
                'is_mandatory' => true,
                'notes' => 'Vaccin vivant atténué. Administrer tôt le matin dans l\'eau de boisson sans chlore. Priver d\'eau 1h avant. Diluer dans de l\'eau froide.',
            ],
            [
                'treatment_name' => 'Gumboro (IBD) — première dose',
                'treatment_type' => 'vaccin',
                'scheduled_day' => 14,
                'administration_route' => 'eau',
                'dosage' => '1 dose/sujet',
                'withdrawal_days' => 0,
                'is_mandatory' => true,
                'notes' => 'Maladie de Gumboro (Bursite infectieuse). Vaccin souche intermédiaire. Priver d\'eau 1h avant vaccination.',
            ],
            [
                'treatment_name' => 'Newcastle B1 (primo-vaccination)',
                'treatment_type' => 'vaccin',
                'scheduled_day' => 21,
                'administration_route' => 'eau',
                'dosage' => '1 dose/sujet',
                'withdrawal_days' => 0,
                'is_mandatory' => true,
                'notes' => 'Maladie de Newcastle, souche B1. Administrer tôt le matin. Peut être combiné avec le rappel Gumboro.',
            ],
            [
                'treatment_name' => 'Gumboro (IBD) — rappel',
                'treatment_type' => 'vaccin',
                'scheduled_day' => 21,
                'administration_route' => 'eau',
                'dosage' => '1 dose/sujet',
                'withdrawal_days' => 0,
                'is_mandatory' => true,
                'notes' => 'Deuxième dose Gumboro. Peut être combinée avec la vaccination Newcastle B1 dans la même eau.',
            ],
            [
                'treatment_name' => 'Newcastle La Sota — rappel',
                'treatment_type' => 'vaccin',
                'scheduled_day' => 28,
                'administration_route' => 'eau',
                'dosage' => '1 dose/sujet',
                'withdrawal_days' => 0,
                'is_mandatory' => true,
                'notes' => 'Rappel Newcastle souche La Sota pour renforcer l\'immunité en fin d\'élevage.',
            ],

            // --- ANTICOCCIDIENS ---
            [
                'treatment_name' => 'Amprolium (anticoccidien préventif)',
                'treatment_type' => 'anticoccidien',
                'scheduled_day' => 10,
                'administration_route' => 'eau',
                'dosage' => '0,5 g/L pendant 5 jours',
                'withdrawal_days' => 5,
                'is_mandatory' => true,
                'notes' => 'Prévention de la coccidiose. Appliquer si l\'aliment n\'est pas supplémenté en coccidiostatique. Respecter le délai d\'attente avant abattage.',
            ],
            [
                'treatment_name' => 'Amprolium (rappel anticoccidien)',
                'treatment_type' => 'anticoccidien',
                'scheduled_day' => 25,
                'administration_route' => 'eau',
                'dosage' => '0,5 g/L pendant 3 jours',
                'withdrawal_days' => 5,
                'is_mandatory' => false,
                'notes' => 'Rappel préventif en milieu d\'élevage. À appliquer si signes cliniques ou historique de coccidiose dans le bâtiment.',
            ],

            // --- VITAMINES & COMPLÉMENTS ---
            [
                'treatment_name' => 'Complexe vitaminé B + C (anti-stress)',
                'treatment_type' => 'vitamine',
                'scheduled_day' => 14,
                'administration_route' => 'eau',
                'dosage' => '1 g/L pendant 2 jours',
                'withdrawal_days' => 0,
                'is_mandatory' => false,
                'notes' => 'Soutien nutritionnel post-vaccination Gumboro. Améliore la réponse immunitaire.',
            ],
            [
                'treatment_name' => 'Vitamines A, D3, E + oligo-éléments',
                'treatment_type' => 'vitamine',
                'scheduled_day' => 21,
                'administration_route' => 'eau',
                'dosage' => '1 mL/L pendant 3 jours',
                'withdrawal_days' => 0,
                'is_mandatory' => false,
                'notes' => 'Complément vitaminé en période de transition alimentaire (démarrage → croissance). Favorise la croissance osseuse.',
            ],
            [
                'treatment_name' => 'Vitamines E + Sélénium (finition)',
                'treatment_type' => 'vitamine',
                'scheduled_day' => 35,
                'administration_route' => 'eau',
                'dosage' => '1 mL/L pendant 3 jours',
                'withdrawal_days' => 0,
                'is_mandatory' => false,
                'notes' => 'Antioxydant en phase de finition. Améliore la qualité de la viande et réduit la mortalité.',
            ],

            // --- VERMIFUGE ---
            [
                'treatment_name' => 'Lévamisole (vermifuge)',
                'treatment_type' => 'vermifuge',
                'scheduled_day' => 30,
                'administration_route' => 'eau',
                'dosage' => '0,5 mL/L pendant 1 jour',
                'withdrawal_days' => 10,
                'is_mandatory' => false,
                'notes' => 'Traitement antiparasitaire interne. À administrer si présence d\'helminthes constatée. Respecter le délai d\'attente avant abattage.',
            ],
        ];

        foreach ($programmesChair as $programme) {
            HealthProgram::firstOrCreate(
                [
                    'organisation_id' => null,
                    'farm_id' => null,
                    'chicken_type_id' => $chair?->id,
                    'treatment_name' => $programme['treatment_name'],
                    'scheduled_day' => $programme['scheduled_day'],
                ],
                $programme,
            );
        }

        // ====================================================================
        // PROGRAMME SANITAIRE — PONDEUSES (cycle 18-20 mois)
        // ====================================================================
        $programmesPondeuse = [
            // --- VACCINS OBLIGATOIRES ---
            [
                'treatment_name' => 'Bronchite Infectieuse H120 (primo-vaccination)',
                'treatment_type' => 'vaccin',
                'scheduled_day' => 7,
                'administration_route' => 'eau',
                'dosage' => '1 dose/sujet',
                'withdrawal_days' => 0,
                'is_mandatory' => true,
                'notes' => 'Vaccin vivant atténué souche H120. Administrer tôt le matin dans l\'eau sans chlore. Priver d\'eau 1h avant.',
            ],
            [
                'treatment_name' => 'Gumboro (IBD) — première dose',
                'treatment_type' => 'vaccin',
                'scheduled_day' => 14,
                'administration_route' => 'eau',
                'dosage' => '1 dose/sujet',
                'withdrawal_days' => 0,
                'is_mandatory' => true,
                'notes' => 'Bursite infectieuse. Première immunisation.',
            ],
            [
                'treatment_name' => 'Newcastle B1 (primo-vaccination)',
                'treatment_type' => 'vaccin',
                'scheduled_day' => 21,
                'administration_route' => 'eau',
                'dosage' => '1 dose/sujet',
                'withdrawal_days' => 0,
                'is_mandatory' => true,
                'notes' => 'Souche B1 — primo-vaccination Newcastle.',
            ],
            [
                'treatment_name' => 'Gumboro (IBD) — rappel',
                'treatment_type' => 'vaccin',
                'scheduled_day' => 28,
                'administration_route' => 'eau',
                'dosage' => '1 dose/sujet',
                'withdrawal_days' => 0,
                'is_mandatory' => true,
                'notes' => 'Deuxième dose Gumboro pour renforcer l\'immunité.',
            ],
            [
                'treatment_name' => 'Bronchite Infectieuse — rappel',
                'treatment_type' => 'vaccin',
                'scheduled_day' => 35,
                'administration_route' => 'eau',
                'dosage' => '1 dose/sujet',
                'withdrawal_days' => 0,
                'is_mandatory' => true,
                'notes' => 'Rappel bronchite infectieuse H120.',
            ],
            [
                'treatment_name' => 'Newcastle La Sota (rappel)',
                'treatment_type' => 'vaccin',
                'scheduled_day' => 42,
                'administration_route' => 'eau',
                'dosage' => '1 dose/sujet',
                'withdrawal_days' => 0,
                'is_mandatory' => true,
                'notes' => 'Rappel Newcastle souche La Sota.',
            ],
            [
                'treatment_name' => 'Variole Aviaire (Fowlpox)',
                'treatment_type' => 'vaccin',
                'scheduled_day' => 56,
                'administration_route' => 'injection',
                'dosage' => '1 dose/sujet (wing-web)',
                'withdrawal_days' => 0,
                'is_mandatory' => true,
                'notes' => 'Vaccination par piqûre alaire (wing-web). Vérifier la prise à 7-10 jours (légère réaction locale).',
            ],
            [
                'treatment_name' => 'Newcastle — rappel 10 semaines',
                'treatment_type' => 'vaccin',
                'scheduled_day' => 70,
                'administration_route' => 'eau',
                'dosage' => '1 dose/sujet',
                'withdrawal_days' => 0,
                'is_mandatory' => true,
                'notes' => 'Rappel Newcastle La Sota à 10 semaines.',
            ],
            [
                'treatment_name' => 'Typhose (Salmonella Gallinarum)',
                'treatment_type' => 'vaccin',
                'scheduled_day' => 84,
                'administration_route' => 'injection',
                'dosage' => '0,5 mL/sujet (sous-cutané)',
                'withdrawal_days' => 0,
                'is_mandatory' => true,
                'notes' => 'Vaccination contre la typhose aviaire. Injection sous-cutanée dans le cou. Fréquente en Afrique de l\'Ouest.',
            ],
            [
                'treatment_name' => 'Newcastle + Bronchite Infectieuse (pré-ponte)',
                'treatment_type' => 'vaccin',
                'scheduled_day' => 112,
                'administration_route' => 'injection',
                'dosage' => '0,5 mL/sujet (intramusculaire)',
                'withdrawal_days' => 0,
                'is_mandatory' => true,
                'notes' => 'Vaccin huileux bivalent Newcastle + Bronchite infectieuse avant entrée en ponte. Injection intramusculaire (poitrine ou cuisse).',
            ],
            [
                'treatment_name' => 'EDS 76 (Egg Drop Syndrome)',
                'treatment_type' => 'vaccin',
                'scheduled_day' => 119,
                'administration_route' => 'injection',
                'dosage' => '0,5 mL/sujet (intramusculaire)',
                'withdrawal_days' => 0,
                'is_mandatory' => true,
                'notes' => 'Prévention de la chute de ponte. Vaccin inactivé huileux. Administrer 1-2 semaines avant l\'entrée en ponte (17-18 semaines).',
            ],
            [
                'treatment_name' => 'Newcastle — rappel en production (6 mois)',
                'treatment_type' => 'vaccin',
                'scheduled_day' => 182,
                'administration_route' => 'eau',
                'dosage' => '1 dose/sujet',
                'withdrawal_days' => 0,
                'is_mandatory' => true,
                'notes' => 'Rappel Newcastle pendant la phase de production pour maintenir l\'immunité. Éviter les périodes de pic de ponte.',
            ],
            [
                'treatment_name' => 'Newcastle — rappel en production (9 mois)',
                'treatment_type' => 'vaccin',
                'scheduled_day' => 280,
                'administration_route' => 'eau',
                'dosage' => '1 dose/sujet',
                'withdrawal_days' => 0,
                'is_mandatory' => true,
                'notes' => 'Rappel Newcastle à 9 mois de production.',
            ],
            [
                'treatment_name' => 'Bronchite Infectieuse — rappel production',
                'treatment_type' => 'vaccin',
                'scheduled_day' => 240,
                'administration_route' => 'eau',
                'dosage' => '1 dose/sujet',
                'withdrawal_days' => 0,
                'is_mandatory' => false,
                'notes' => 'Rappel bronchite infectieuse en cours de production. À adapter selon les résultats sérologiques.',
            ],

            // --- ANTICOCCIDIENS ---
            [
                'treatment_name' => 'Amprolium (anticoccidien préventif)',
                'treatment_type' => 'anticoccidien',
                'scheduled_day' => 10,
                'administration_route' => 'eau',
                'dosage' => '0,5 g/L pendant 5 jours',
                'withdrawal_days' => 0,
                'is_mandatory' => true,
                'notes' => 'Prévention coccidiose pendant la phase poussin. Continuer jusqu\'à J42 si l\'aliment n\'est pas coccidiostatique.',
            ],
            [
                'treatment_name' => 'Toltrazuril (traitement coccidiose)',
                'treatment_type' => 'anticoccidien',
                'scheduled_day' => 30,
                'administration_route' => 'eau',
                'dosage' => '7 mg/kg de poids vif pendant 2 jours',
                'withdrawal_days' => 14,
                'is_mandatory' => false,
                'notes' => 'À utiliser uniquement en cas de suspicion ou confirmation de coccidiose clinique.',
            ],

            // --- VITAMINES & COMPLÉMENTS ---
            [
                'treatment_name' => 'Complexe vitaminé (démarrage)',
                'treatment_type' => 'vitamine',
                'scheduled_day' => 1,
                'administration_route' => 'eau',
                'dosage' => '1 g/L pendant 3 jours',
                'withdrawal_days' => 0,
                'is_mandatory' => false,
                'notes' => 'Vitamines et électrolytes dès l\'arrivée des poussins.',
            ],
            [
                'treatment_name' => 'Vitamines A, D3, E — croissance',
                'treatment_type' => 'vitamine',
                'scheduled_day' => 42,
                'administration_route' => 'eau',
                'dosage' => '1 mL/L pendant 3 jours',
                'withdrawal_days' => 0,
                'is_mandatory' => false,
                'notes' => 'Soutien vitaminé en phase de croissance. Favorise le développement osseux et immunitaire.',
            ],
            [
                'treatment_name' => 'Calcium + Vitamine D3 (pré-ponte)',
                'treatment_type' => 'vitamine',
                'scheduled_day' => 105,
                'administration_route' => 'aliment',
                'dosage' => 'Aliment pré-ponte spécifique',
                'withdrawal_days' => 0,
                'is_mandatory' => true,
                'notes' => 'Transition vers l\'aliment pré-pondeuse (15-16 semaines). Essentiel pour la minéralisation osseuse avant la ponte.',
            ],
            [
                'treatment_name' => 'Vitamines B12 + Acide folique (production)',
                'treatment_type' => 'vitamine',
                'scheduled_day' => 140,
                'administration_route' => 'eau',
                'dosage' => '1 mL/L pendant 3 jours',
                'withdrawal_days' => 0,
                'is_mandatory' => false,
                'notes' => 'Stimule la production d\'œufs et améliore la qualité de la coquille.',
            ],

            // --- VERMIFUGES ---
            [
                'treatment_name' => 'Fenbendazole (vermifuge)',
                'treatment_type' => 'vermifuge',
                'scheduled_day' => 112,
                'administration_route' => 'aliment',
                'dosage' => '6 mg/kg de poids vif pendant 5 jours',
                'withdrawal_days' => 7,
                'is_mandatory' => false,
                'notes' => 'Vermifugation avant entrée en ponte. Efficace contre ascaris, capillaires, hétérakis. Respecter le délai avant collecte des œufs.',
            ],
            [
                'treatment_name' => 'Lévamisole (vermifuge production)',
                'treatment_type' => 'vermifuge',
                'scheduled_day' => 240,
                'administration_route' => 'eau',
                'dosage' => '0,5 mL/L pendant 1 jour',
                'withdrawal_days' => 7,
                'is_mandatory' => false,
                'notes' => 'Rappel vermifuge en cours de production. Ne pas utiliser pendant les 2 jours avant collecte.',
            ],

            // --- ANTIPARASITAIRES ---
            [
                'treatment_name' => 'Ivermectine (poux, acariens)',
                'treatment_type' => 'antiparasitaire',
                'scheduled_day' => 168,
                'administration_route' => 'injection',
                'dosage' => '0,2 mg/kg de poids vif',
                'withdrawal_days' => 28,
                'is_mandatory' => false,
                'notes' => 'Traitement contre les ectoparasites (poux rouges, acariens). Long délai d\'attente pour les œufs — traitement à planifier en période de faible production.',
            ],
        ];

        foreach ($programmesPondeuse as $programme) {
            HealthProgram::firstOrCreate(
                [
                    'organisation_id' => null,
                    'farm_id' => null,
                    'chicken_type_id' => $pondeuse?->id,
                    'treatment_name' => $programme['treatment_name'],
                    'scheduled_day' => $programme['scheduled_day'],
                ],
                $programme,
            );
        }
    }
}
