const pageTitle = document.getElementById('pageTitle');
const pageUrl = document.getElementById('pageUrl');
const candidateCount = document.getElementById('candidateCount');
const selectedCount = document.getElementById('selectedCount');
const terms = document.getElementById('terms');
const emptyMessage = document.getElementById('emptyMessage');
const selectAllButton = document.getElementById('selectAllButton');
const clearAllButton = document.getElementById('clearAllButton');
const generateQuestionsButton = document.getElementById('generateQuestionsButton');
const nextStepMessage = document.getElementById('nextStepMessage');
const generationProgress = document.getElementById('generationProgress');
const generationPhase = document.getElementById('generationPhase');
let currentPageAnalysis = null;
let generatingQuestions = false;
let generationPhaseTimer = null;
const appLink = document.getElementById('appLink');

const generationPhases = [
    '用語を整理しています',
    '問題を作っています',
    '選択肢を整えています',
];

function getSourceUrl() {
    return new URLSearchParams(location.search).get('source') ?? '';
}

async function initialize() {
    const sourceUrl = getSourceUrl();
    await loadAppLink();

    if (!sourceUrl) {
        pageTitle.textContent = '元ページを特定できませんでした。';
        emptyMessage.hidden = false;
        return;
    }

    const { pageAnalyses = {} } = await chrome.storage.local.get(['pageAnalyses']);
    const pageAnalysis = pageAnalyses[sourceUrl];
    const termList = pageAnalysis?.terms ?? [];

    currentPageAnalysis = pageAnalysis ?? null;
    pageTitle.textContent = pageAnalysis?.title ?? 'タイトルなし';
    pageUrl.textContent = sourceUrl;
    candidateCount.textContent = formatNumber(termList.length);
    emptyMessage.hidden = termList.length > 0;
    renderTerms(termList);
}

async function loadAppLink() {
    try {
        const response = await chrome.runtime.sendMessage({ action: 'getAppUrl' });

        if (response?.url) {
            appLink.href = response.url;
        }
    } catch (error) {
        console.debug('[Web Learning] アプリリンクを取得できませんでした:', error.message);
    }
}

function renderTerms(termList) {
    terms.replaceChildren();

    termList.forEach((item, index) => {
        const label = document.createElement('label');
        label.className = 'term';

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.checked = true;
        checkbox.dataset.termIndex = String(index);
        checkbox.addEventListener('change', updateSelectedCount);

        const content = document.createElement('div');
        const name = document.createElement('div');
        name.className = 'term-name';
        name.textContent = item?.term ?? '';

        const description = document.createElement('div');
        description.className = 'term-description';
        description.textContent = item?.description ?? '';

        content.append(name, description);
        label.append(checkbox, content);
        terms.appendChild(label);
    });

    updateSelectedCount();
}

function setAllChecked(checked) {
    terms.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
        checkbox.checked = checked;
    });

    updateSelectedCount();
}

function updateSelectedCount() {
    const count = terms.querySelectorAll('input[type="checkbox"]:checked').length;
    selectedCount.textContent = `選択中：${formatNumber(count)}件`;
}

function getSelectedTerms() {
    if (!currentPageAnalysis?.terms) {
        return [];
    }

    return [...terms.querySelectorAll('input[type="checkbox"]:checked')]
        .map((checkbox) => currentPageAnalysis.terms[Number(checkbox.dataset.termIndex)])
        .filter((term) => term?.term && term?.description)
        .map((term) => ({
            term: term.term,
            description: term.description,
        }));
}

function setGenerationMessage(message, isError = false) {
    nextStepMessage.textContent = message;
    nextStepMessage.style.color = isError ? '#b91c1c' : '';
}

function setGeneratingState(isGenerating) {
    generationProgress.hidden = !isGenerating;
    generateQuestionsButton.hidden = isGenerating;
    nextStepMessage.hidden = isGenerating;
    generateQuestionsButton.textContent = isGenerating ? '生成中…' : '選択した用語から問題を作る';

    if (!isGenerating) {
        window.clearInterval(generationPhaseTimer);
        generationPhaseTimer = null;
        return;
    }

    let phaseIndex = 0;
    generationPhase.textContent = generationPhases[phaseIndex];
    generationPhaseTimer = window.setInterval(() => {
        phaseIndex = (phaseIndex + 1) % generationPhases.length;
        generationPhase.textContent = generationPhases[phaseIndex];
    }, 2200);
}

async function generateQuestions() {
    if (generatingQuestions) {
        return;
    }

    const selectedTerms = getSelectedTerms();

    if (selectedTerms.length === 0) {
        setGenerationMessage('問題を作る用語を1件以上選択してください', true);
        return;
    }

    if (selectedTerms.length > 20) {
        setGenerationMessage('一度に問題を作れる用語は20件までです', true);
        return;
    }

    if (!currentPageAnalysis?.url || !currentPageAnalysis?.title) {
        setGenerationMessage('元ページ情報を取得できませんでした。', true);
        return;
    }

    generatingQuestions = true;
    generateQuestionsButton.disabled = true;
    setGenerationMessage('問題を生成しています…');
    setGeneratingState(true);

    try {
        const { captureAccessToken = '' } = await chrome.storage.local.get(['captureAccessToken']);
        if (!captureAccessToken) {
            throw new Error('先にWeb Learningと接続してください。');
        }
        const api = await chrome.runtime.sendMessage({ action: 'getApiUrl', path: 'extension/generate-quiz' });
        if (!api?.url) {
            throw new Error('Web Learningの接続先を確認できません。');
        }
        const response = await fetch(api.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                Authorization: `Bearer ${captureAccessToken}`,
            },
            body: JSON.stringify({
                title: currentPageAnalysis.title,
                url: currentPageAnalysis.url,
                terms: selectedTerms,
            }),
        });
        const result = await response.json().catch(() => ({}));

        if (response.status === 401) {
            await chrome.storage.local.set({ captureAccessToken: '', learningMode: false });
            throw new Error('Learning Modeの接続が期限切れです。Popupからもう一度ONにしてください。');
        }

        if (!response.ok || !result.preview_url) {
            throw new Error(result.message ?? '問題の生成に失敗しました。');
        }

        await chrome.tabs.create({ url: result.preview_url });
        setGenerationMessage('問題を生成しました。一時クイズを新しいタブで開きました。');
    } catch (error) {
        console.error('[Web Learning] 問題生成エラー:', error);
        setGenerationMessage(error.message ?? '問題の生成に失敗しました。', true);
    } finally {
        generatingQuestions = false;
        generateQuestionsButton.disabled = false;
        setGeneratingState(false);
    }
}

function formatNumber(value) {
    return new Intl.NumberFormat('ja-JP').format(value);
}

selectAllButton.addEventListener('click', () => setAllChecked(true));
clearAllButton.addEventListener('click', () => setAllChecked(false));
generateQuestionsButton.addEventListener('click', () => void generateQuestions());

void initialize();
