<?php
// Courses with learning outcome keywords used for feedback scoring
$COURSES = [
    'mathematics' => [
        'name' => 'Mathematics',
        'icon' => '📐',
        'color' => '#3b82f6',
        'keywords' => ['calculation','equation','formula','geometry','algebra','statistics','data','measurement','graph','number','probability','function','ratio','percentage','average','median','mean','angle','area','volume','pattern','sequence'],
        'outcomes' => ['Apply mathematical calculations','Analyze data statistically','Use geometric principles','Create graphs and models']
    ],
    'physics' => [
        'name' => 'Physics',
        'icon' => '⚡',
        'color' => '#f59e0b',
        'keywords' => ['force','energy','motion','velocity','acceleration','mass','gravity','electricity','magnetism','wave','light','heat','pressure','sensor','signal','circuit','power','current','voltage','friction','momentum','Newton'],
        'outcomes' => ['Understand forces and motion','Apply energy concepts','Work with electrical systems','Analyze waves and signals']
    ],
    'chemistry' => [
        'name' => 'Chemistry',
        'icon' => '🧪',
        'color' => '#10b981',
        'keywords' => ['reaction','molecule','element','compound','acid','base','solution','mixture','catalyst','bond','atom','periodic','chemical','substance','pH','concentration','experiment','laboratory'],
        'outcomes' => ['Understand chemical reactions','Identify elements and compounds','Apply lab methods','Analyze substances']
    ],
    'biology' => [
        'name' => 'Biology',
        'icon' => '🌱',
        'color' => '#22c55e',
        'keywords' => ['cell','organism','ecosystem','genetics','evolution','plant','animal','body','health','species','habitat','biodiversity','photosynthesis','metabolism','DNA','protein','bacteria','virus','anatomy'],
        'outcomes' => ['Study living organisms','Analyze ecosystems','Apply biological concepts','Understand health']
    ],
    'history' => [
        'name' => 'History',
        'icon' => '📜',
        'color' => '#a78bfa',
        'keywords' => ['historical','timeline','event','cause','effect','civilization','war','culture','society','period','era','source','evidence','change','continuity','revolution','movement','empire','democracy'],
        'outcomes' => ['Analyze historical events','Understand cause and effect','Evaluate primary sources','Study civilizations']
    ],
    'geography' => [
        'name' => 'Geography',
        'icon' => '🌍',
        'color' => '#06b6d4',
        'keywords' => ['map','location','region','climate','environment','population','urban','rural','resource','sustainability','migration','landscape','topography','country','continent','ocean','weather','temperature'],
        'outcomes' => ['Understand spatial relationships','Analyze environmental systems','Study human geography','Use map skills']
    ],
    'literature' => [
        'name' => 'Literature / Dutch',
        'icon' => '📚',
        'color' => '#f97316',
        'keywords' => ['text','story','narrative','character','theme','language','writing','reading','analysis','argument','communication','report','essay','genre','poetry','fiction','author','structure','style'],
        'outcomes' => ['Analyze texts critically','Communicate effectively','Write structured arguments','Understand genre']
    ],
    'english' => [
        'name' => 'English',
        'icon' => '🗣️',
        'color' => '#ec4899',
        'keywords' => ['communication','language','writing','speaking','reading','grammar','vocabulary','presentation','speech','report','interview','article','summary','translate','international'],
        'outcomes' => ['Communicate in English','Write clearly in English','Present ideas','Understand global context']
    ],
    'visual_arts' => [
        'name' => 'Visual Arts',
        'icon' => '🎨',
        'color' => '#e879f9',
        'keywords' => ['design','visual','color','composition','creative','artistic','draw','sketch','illustration','model','aesthetic','art','style','typography','layout','prototype','render','image'],
        'outcomes' => ['Create visual designs','Apply artistic principles','Develop creative thinking','Build prototypes']
    ],
    'music' => [
        'name' => 'Music',
        'icon' => '🎵',
        'color' => '#fb7185',
        'keywords' => ['rhythm','melody','harmony','sound','tempo','beat','musical','instrument','frequency','pitch','notation','audio','vibration','acoustic','digital sound','recording'],
        'outcomes' => ['Understand musical elements','Create or analyze music','Apply sound science','Work with audio']
    ],
    'pe' => [
        'name' => 'Physical Education',
        'icon' => '🏃',
        'color' => '#84cc16',
        'keywords' => ['movement','exercise','sport','body','health','fitness','motor','coordination','team','physical','strength','endurance','agility','training','biomechanics'],
        'outcomes' => ['Develop physical skills','Understand health and fitness','Apply movement concepts','Work in teams']
    ],
    'computer_science' => [
        'name' => 'Computer Science',
        'icon' => '💻',
        'color' => '#00c9ff',
        'keywords' => ['program','code','algorithm','data','software','digital','variable','function','loop','condition','network','database','interface','app','website','artificial intelligence','machine learning','automation'],
        'outcomes' => ['Write and debug code','Design algorithms','Build digital solutions','Understand networks']
    ],
    'economics' => [
        'name' => 'Economics',
        'icon' => '💰',
        'color' => '#fbbf24',
        'keywords' => ['market','supply','demand','price','cost','budget','resource','production','consumer','business','trade','economy','financial','profit','investment','entrepreneur','salary','tax'],
        'outcomes' => ['Understand economic systems','Analyze markets','Apply financial concepts','Plan budgets']
    ],
];

/**
 * Calculate what percentage of learning outcome keywords are covered in the generated text.
 */
function checkLearningOutcomes(string $generatedText, array $selectedCourses, array $coursesData): float {
    $totalKeywords = 0;
    $foundKeywords = 0;
    $textLower = strtolower($generatedText);

    foreach ($selectedCourses as $courseKey) {
        if (!isset($coursesData[$courseKey])) continue;
        $keywords = $coursesData[$courseKey]['keywords'];
        $totalKeywords += count($keywords);
        foreach ($keywords as $kw) {
            if (str_contains($textLower, strtolower($kw))) {
                $foundKeywords++;
            }
        }
    }

    return $totalKeywords > 0 ? round(($foundKeywords / $totalKeywords) * 100, 1) : 0;
}
