<?php
App::import('Model', 'Transcription');

class TranscriptionTestCase extends CakeTestCase {
    var $fixtures = array(
        'app.contribution',
        'app.favorites_user',
        'app.group',
        'app.language',
        'app.link',
        'app.reindex_flag',
        'app.sentence',
        'app.sentence_annotation',
        'app.sentence_comment',
        'app.sentences_list',
        'app.sentences_sentences_list',
        'app.tag',
        'app.tags_sentence',
        'app.transcription',
        'app.user',
        'app.wall',
        'app.wall_thread',
    );

    function startTest() {
        $this->Transcription =& ClassRegistry::init('Transcription');
        $this->AutoTranscr = $this->_installAutotranscriptionMock();
        $this->AutoTranscr->setReturnValue('jpn_Jpan_to_Hrkt_generate', 'autogenerated furigana');
        $this->AutoTranscr->setReturnValue('jpn_Jpan_to_Hrkt_validate', true);
        $this->AutoTranscr->setReturnValue(
            'cmn_Hans_to_Hant_generate',
            'converted into traditional characters'
        );
        $this->AutoTranscr->setReturnValue(
            'cmn_Hans_to_Latn_generate',
            'autogenerated pinyin'
        );
        $this->AutoTranscr->setReturnValue(
            'yue_Hant_to_Latn_generate',
            'autogenerated jyutping'
        );
    }

    function endTest() {
        unset($this->Transcription);
        ClassRegistry::flush();
    }

    function _installAutotranscriptionMock() {
        Mock::generate('Autotranscription');
        $autotranscription =& new MockAutotranscription;
        $this->Transcription->setAutotranscription($autotranscription);
        return $autotranscription;
    }

    function _getRecord($record) {
        return $this->_fixtures['app.transcription']->records[$record];
    }

    function _saveRecordWith($record, $changedFields) {
        $data = $this->_getRecord($record);
        $this->Transcription->deleteAll(array('1=1'));
        unset($data['id']);
        $data = array_merge($data, $changedFields);
        return (bool)$this->Transcription->save($data);
    }

    function _saveRecordWithout($record, $missingFields) {
        $data = $this->_getRecord($record);
        $this->Transcription->deleteAll(array('1=1'));
        unset($data['id']);
        foreach ($missingFields as $field) {
            unset($data[$field]);
        }
        return (bool)$this->Transcription->save($data);
    }

    function _assertValidRecordWith($record, $changedFields) {
        $this->assertTrue($this->_saveRecordWith($record, $changedFields));
    }
    function _assertValidRecordWithout($record, $changedFields) {
        $this->assertTrue($this->_saveRecordWithout($record, $changedFields));
    }
    function _assertInvalidRecordWith($record, $changedFields) {
        $this->assertFalse($this->_saveRecordWith($record, $changedFields));
    }
    function _assertInvalidRecordWithout($record, $missingFields) {
        $this->assertFalse($this->_saveRecordWithout($record, $missingFields));
    }

    function testValidateFirstRecord() {
        $this->_assertValidRecordWith(0, array());
    }

    function testScriptMustBeValid() {
        $this->_assertInvalidRecordWith(0, array('script' => 'ABCD'));
    }
    function testScriptRequired() {
        $this->_assertInvalidRecordWithout(0, array('script'));
    }
    function testScriptCantBeUpdated() {
        $this->Transcription->delete(1); // to avoid uniqness error
        $data = array('id' => 2, 'script' => 'Hrkt');

        $result = (bool)$this->Transcription->save($data);

        $this->assertFalse($result);
    }

    function testTextCantBeEmpty() {
        $this->_assertInvalidRecordWith(0, array('text' => ''));
    }
    function testTextRequired() {
        $this->_assertInvalidRecordWithout(0, array('text'));
    }

    function testSentenceIdCantBeEmpty() {
        $this->_assertInvalidRecordWith(0, array('sentence_id' => null));
    }
    function testSentenceIdRequired() {
        $this->_assertInvalidRecordWithout(0, array('sentence_id'));
    }
    function testSentenceIdCantBeUpdated() {
        $this->Transcription->delete(3); // to avoid uniqness error
        $data = array('id' => 1, 'sentence_id' => 10);

        $result = (bool)$this->Transcription->save($data);

        $this->assertFalse($result);
    }

    function testCreatedCantBeEmpty() {
        $this->_assertInvalidRecordWith(0, array('created' => ''));
    }
    function testCreatedIsAutomaticallySet() {
        $this->_assertValidRecordWithout(0, array('created'));
    }

    function testUserIdMustBeNumeric() {
        $this->_assertInvalidRecordWith(0, array('user_id' => 'melon'));
    }
    function testUserIdNotRequired() {
        $this->_assertValidRecordWithout(0, array('user_id'));
    }

    function testModifiedCantBeEmpty() {
        $this->_assertInvalidRecordWith(0, array('modified' => ''));
    }
    function testModifiedIsAutomaticallySet() {
        $this->_assertValidRecordWithout(0, array('created'));
    }

    function testTranscriptionMustBeUniqueForASentenceAndAScriptOnCreate() {
        $data = $this->_getRecord(0);
        unset($data['id']);

        $result = (bool)$this->Transcription->save($data);

        $this->assertFalse($result);
    }

    function testJapaneseCanBeTranscriptedToKanas() {
        $jpnSentence = $this->Transcription->Sentence->find('first', array(
            'conditions' => array('Sentence.lang' => 'jpn')
        ));
        $result = $this->Transcription->transcriptableToWhat($jpnSentence);
        $this->assertTrue(isset($result['Hrkt']));
    }

    function testEditTrancriptionText() {
        $result = $this->Transcription->save(array(
            'id' => 3, 'text' => 'we change this'
        ));
        $this->assertTrue($result);
    }
    function testEditTrancriptionTextCantBeEmpty() {
        $result = $this->Transcription->save(array(
            'id' => 3, 'text' => ''
        ));
        $this->assertFalse($result);
    }

    function testCantSaveTranscriptionWithInvalidParent() {
        $nonexistantSentenceId = 52715278;
        $result = $this->Transcription->save(array(
            'sentence_id' => $nonexistantSentenceId,
            'script' => 'Latn',
            'text' => 'Transcription with invalid parent.',
        ));
        $this->assertFalse($result);
    }

    function testCantSaveNotAllowedTranscriptionOnInsert() {
        $englishSentenceId = 1;
        $result = $this->Transcription->save(array(
            'sentence_id' => $englishSentenceId,
            'script' => 'Latn',
            'text' => 'Transcript of English into Latin script??',
        ));
        $this->assertFalse($result);
    }

    function testCantSaveNotAllowedTranscriptionOnUpdate() {
        $result = $this->Transcription->save(array(
            'id' => 1,
            'script' => 'Jpan',
            'text' => 'Transcript of Japanese into Japanese??',
        ));
        $this->assertFalse($result);
    }

    function testGenerateTranscriptionCallsGenerator() {
        $jpnSentence = $this->Transcription->Sentence->find('first', array(
            'conditions' => array('Sentence.lang' => 'jpn')
        ));
        $this->AutoTranscr->expectOnce(
            'jpn_Jpan_to_Hrkt_generate',
            array($jpnSentence['Sentence']['text'])
        );

        $this->Transcription->generateTranscription($jpnSentence, 'Hrkt');
    }

    function testGenerateTranscriptionReturnsTranscription() {
        $jpnSentence = $this->Transcription->Sentence->findById(6);

        $result = $this->Transcription->generateTranscription($jpnSentence, 'Hrkt');
        $expected = array(
            'sentence_id' => 6,
            'script' => 'Hrkt',
            'text' => 'autogenerated furigana',
            'user_id' => null,
            'readonly' => false,
            'needsReview' => true,
            'type' => 'altscript',
            'id' => 'autogenerated',
        );
        $this->assertEqual($expected, $result);
    }

    function testGenerateTranscriptionReturnsTranscriptionWithParent() {
        $this->Transcription->deleteAll('1=1');
        $jpnSentence = $this->Transcription->Sentence->findById(6);
        $expected = array(
            'id' => 'autogenerated',
            'sentence_id' => 6,
            'script' => 'Hrkt',
            'text' => 'autogenerated furigana',
            'user_id' => null,
            'readonly' => false,
            'type' => 'altscript',
            'needsReview' => true,
        );

        $result = $this->Transcription->generateTranscription($jpnSentence, 'Hrkt');

        $this->assertEqual($expected, $result);
    }

    function testFindOnExistingRecordsReturnsReadonlyField() {
        $transcr = $this->Transcription->findById(1);
        $result = array_key_exists('readonly', $transcr['Transcription']);
        $this->assertTrue($result);
    }

    function testGenerateTranscriptionCreatesGenerated() {
        $this->Transcription->deleteAll('1=1');
        $jpnSentence = $this->Transcription->Sentence->findById(10);

        $this->Transcription->generateTranscription($jpnSentence, 'Hrkt', true);

        $created = $this->Transcription->find('count');
        $this->assertEqual(1, $created);
    }

    function testGenerateTranscriptionCreatesProvidedTranscription() {
        $this->Transcription->deleteAll('1=1');
        $jpnSentence = $this->Transcription->Sentence->findById(10);
        $data = array(
            'text' => 'あああ',
            'sentence_id' => 10,
            'script' => 'Hrkt',
            'user_id' => 33,
        );

        $created = $this->Transcription->generateTranscription($jpnSentence, 'Hrkt', true, $data);

        unset($created['modified']);
        unset($created['created']);
        $expected = array(
            'id' => 4,
            'sentence_id' => 10,
            'script' => 'Hrkt',
            'text' => 'あああ',
            'user_id' => 33,
            'readonly' => false,
            'needsReview' => true,
            'type' => 'altscript',
        );
        $this->assertEqual($expected, $created);
    }

    function testGenerateTranscriptionUpdatesProvidedTranscription() {
        $jpnSentence = $this->Transcription->Sentence->findById(10);
        $data = array(
            'id' => '3',
            'text' => 'あああ',
            'sentence_id' => 10,
            'script' => 'Hrkt',
            'user_id' => 33,
        );

        $updated = $this->Transcription->generateTranscription($jpnSentence, 'Hrkt', true, $data);

        unset($updated['modified']);
        unset($updated['created']);
        $expected = array(
            'id' => 3,
            'sentence_id' => 10,
            'script' => 'Hrkt',
            'text' => 'あああ',
            'user_id' => 33,
            'readonly' => false,
            'needsReview' => true,
            'type' => 'altscript',
        );
        $this->assertEqual($expected, $updated);
    }

    function testGenerateTranscriptionUpdates() {
        $transcr = $this->Transcription->findById(1);
        $jpnSentence = $this->Transcription->Sentence->findById(
            $transcr['Transcription']['sentence_id']
        );
        $transcr['Transcription']['text'] = 'あああ';

        $this->Transcription->generateTranscription(
            $jpnSentence, 'Hrkt', true, $transcr['Transcription']
        );

        $updated = $this->Transcription->find('all', array(
            'conditions' => array(
                'sentence_id' => $transcr['Transcription']['sentence_id']
            )
        ));
        $this->assertEqual('あああ', $updated[0]['Transcription']['text']);
    }

    function testGenerateAndSaveAllTranscriptionsForJapanese() {
        $this->Transcription->deleteAll('1=1');
        $jpnSentence = $this->Transcription->Sentence->findById(6);

        $this->Transcription->generateAndSaveAllTranscriptionsFor($jpnSentence);

        $created = $this->Transcription->find('count', array(
            'conditions' => array('sentence_id' => 6)
        ));
        $this->assertEqual(1, $created);
    }

    function testGenerateAndSaveAllTranscriptionsReturnValue() {
        $this->Transcription->deleteAll('1=1');
        $cmnSentence = $this->Transcription->Sentence->findById(2);

        $returned = $this->Transcription->generateAndSaveAllTranscriptionsFor($cmnSentence);

        $created = $this->Transcription->find('count', array(
            'conditions' => array('sentence_id' => 2)
        ));
        $this->assertEqual($created, $returned);
    }

    function testGenerateAndSaveAllTranscriptionsForChinese() {
        $this->Transcription->deleteAll('1=1');
        $cmnSentence = $this->Transcription->Sentence->findById(2);

        $this->Transcription->generateAndSaveAllTranscriptionsFor($cmnSentence);

        $created = $this->Transcription->find('count', array(
            'conditions' => array('sentence_id' => 2)
        ));
        $this->assertEqual(2, $created);
    }

    function testGenerateAndSaveTranscriptionsForCantonese() {
        $this->Transcription->deleteAll('1=1');
	$yueSentenceId = 11;
        $yueSentence = $this->Transcription->Sentence->findById($yueSentenceId);

        $this->Transcription->generateTranscription($yueSentence, 'Latn', true);

        $created = $this->Transcription->find('count', array(
            'conditions' => array('sentence_id' => $yueSentenceId)
        ));
        $this->assertEqual(1, $created);
    }

    function testCanCreateReadonlyTranscriptions() {
        $this->_assertValidRecordWith(1, array());
    }

    function testCannotUpdateReadonlyTranscriptions() {
        $result = (bool)$this->Transcription->saveTranscription(array(
            'id' => 2,
            'sentence_id' => 2,
            'script' => 'Hant',
            'text' => '問題的根源是，在當今世界，愚人充滿了自信，而智者充滿了懷疑。',
        ));
        $this->assertFalse($result);
    }

    function testSaveTranscriptionChecksUserProvidedTranscriptionValidityOnCreate() {
        $transcr = $this->Transcription->find('first', array(
            'conditions' => array('sentence_id' => 10)
        ));
        $this->Transcription->delete($transcr['Transcription']['id'], false);
        unset($transcr['Transcription']['id']);
        $transcr['Transcription']['user_id'] = 4;

        $this->AutoTranscr = $this->_installAutotranscriptionMock();
        $this->AutoTranscr->setReturnValue('jpn_Jpan_to_Hrkt_validate', false);

        $result = (bool)$this->Transcription->saveTranscription($transcr['Transcription']);
        $this->assertFalse($result);
    }

    function testSaveTranscriptionDontCheckGeneratedTranscriptionValidityOnCreate() {
        $transcr = $this->Transcription->find('first', array(
            'conditions' => array('sentence_id' => 10)
        ));
        $this->Transcription->delete($transcr['Transcription']['id'], false);
        unset($transcr['Transcription']['id']);

        $this->AutoTranscr = $this->_installAutotranscriptionMock();
        $this->AutoTranscr->setReturnValue('jpn_Jpan_to_Hrkt_validate', false);

        $result = (bool)$this->Transcription->saveTranscription($transcr['Transcription']);
        $this->assertTrue($result);
    }

    function testSaveTranscriptionChecksUserProvidedTranscriptionValidityOnUpdate() {
        $transcr = $this->Transcription->find('first', array(
            'conditions' => array('sentence_id' => 10)
        ));
        $transcr['Transcription']['text'] = 'something new';
        $transcr['Transcription']['user_id'] = 4;

        $this->AutoTranscr = $this->_installAutotranscriptionMock();
        $this->AutoTranscr->setReturnValue('jpn_Jpan_to_Hrkt_validate', false);

        $result = (bool)$this->Transcription->saveTranscription($transcr['Transcription']);
        $this->assertFalse($result);
    }

    function testSaveTranscriptionDontCheckGeneratedTranscriptionValidityOnUpdate() {
        $transcr = $this->Transcription->find('first', array(
            'conditions' => array('sentence_id' => 10)
        ));
        $transcr['Transcription']['text'] = 'something new';

        $this->AutoTranscr = $this->_installAutotranscriptionMock();
        $this->AutoTranscr->setReturnValue('jpn_Jpan_to_Hrkt_validate', false);

        $result = (bool)$this->Transcription->saveTranscription($transcr['Transcription']);
        $this->assertTrue($result);
    }

    function testAddGeneratedTranscriptionsAddsEverything() {
        $this->Transcription->deleteAll('1=1');
        $jpnSentence = $this->Transcription->Sentence->findById(10);

        $result = $this->Transcription->addGeneratedTranscriptions(
            array(),
            $jpnSentence
        );

        $this->assertEqual(1, count($result));
        $this->assertEqual('Hrkt', $result[0]['script']);
    }

    function testAddGeneratedTranscriptionsDontDoubleGenerate() {
        $this->Transcription->deleteAll('1=1');
        $jpnSentence = $this->Transcription->Sentence->findById(10);

        Mock::generatePartial('Transcription', 'MockTranscription', array('generateTranscription'));
        $this->Transcription =& new MockTranscription;
        $this->Transcription->setReturnValue('generateTranscription', array());
        $this->Transcription->expectCallCount('generateTranscription', 1);

        $this->Transcription->addGeneratedTranscriptions(
            array(),
            $jpnSentence
        );
    }

    function testAddGeneratedTranscriptionsAddsNothing() {
        $jpnSentence = $this->Transcription->Sentence->findById(6);
        $existingTranscriptions = $this->Transcription->find(
            'all',
            array('conditions' => array('sentence_id' => 6))
        );
        $existingTranscriptions = Set::classicExtract(
            $existingTranscriptions,
            '{n}.Transcription'
        );

        $result = $this->Transcription->addGeneratedTranscriptions(
            $existingTranscriptions,
            $jpnSentence
        );

        $this->assertEqual(1, count($result));
        $this->assertEqual('Hrkt', $result[0]['script']);
    }

    function testAddGeneratedTranscriptionsKeepsOrder() {
        $cmnSentenceId = 2;
        $cmnSentence = $this->Transcription->Sentence->findById($cmnSentenceId);
        $result = $this->Transcription->saveTranscription(array(
            'sentence_id' => $cmnSentenceId,
            'script' => 'Latn',
            'text' => 'blah blah blah in pinyin',
        ));

        $result = $this->Transcription->addGeneratedTranscriptions(
            array($result),
            $cmnSentence
        );

        $this->assertEqual('Hant', $result[0]['script']);
        $this->assertEqual('Latn', $result[1]['script']);
    }

    function testDetectScriptCallsDetector() {
        $cmnSentence = $this->Transcription->Sentence->find('first', array(
            'conditions' => array('Sentence.lang' => 'cmn')
        ));
        $this->AutoTranscr->expectOnce(
            'cmn_detectScript',
            array($cmnSentence['Sentence']['text'])
        );

        $this->Transcription->detectScript(
            $cmnSentence['Sentence']['lang'],
            $cmnSentence['Sentence']['text']
        );
    }
}